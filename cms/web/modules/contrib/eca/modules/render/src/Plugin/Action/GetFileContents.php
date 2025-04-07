<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Render file contents.
 *
 * @Action(
 *   id = "eca_render_file_contents",
 *   label = @Translation("Render: file contents"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetFileContents extends RenderElementActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uri' => '',
      'encoding' => 'string:base64',
      'token_mime_type' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI'),
      '#description' => $this->t('Either the relative path of the file or a full URL.'),
      '#weight' => -100,
      '#default_value' => $this->configuration['uri'],
      '#required' => TRUE,
    ];
    $form['encoding'] = [
      '#type' => 'select',
      '#title' => $this->t('Encoding'),
      '#options' => [
        'img:base64' => $this->t('HTML image, base64 encoded'),
        'string:base64' => $this->t('String, base64 encoded'),
        'string:raw' => $this->t('String, not encoded'),
      ],
      '#weight' => -90,
      '#default_value' => $this->configuration['encoding'],
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['token_mime_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name for MIME type'),
      '#description' => $this->t('Optionally define the name of a token, that stores the detected MIME type of the file.'),
      '#weight' => -80,
      '#default_value' => $this->configuration['token_mime_type'],
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    if (!function_exists('mime_content_type')) {
      $form_state->setError($form['uri'], $this->t('The PHP extension "fileinfo" is not available. It must be installed for being able to get file contents with this action.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['uri'] = $form_state->getValue('uri');
    $this->configuration['encoding'] = $form_state->getValue('encoding');
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $uri = trim((string) $this->tokenService->replaceClear($this->configuration['uri']));
    if ($uri === '') {
      throw new \InvalidArgumentException("No URI given for rendering file contents.");
    }
    if (!function_exists('mime_content_type')) {
      throw new \RuntimeException('The PHP extension "fileinfo" is not available. It must be installed for being able to get file contents with this action.');
    }

    $file_contents = @file_get_contents($uri);
    if ($file_contents === FALSE) {
      throw new \RuntimeException("Failed to read file contents in \"eca_render_file_contents\".");
    }
    $mime_type = @mime_content_type($uri);

    if ($this->configuration['token_mime_type'] !== '') {
      $this->tokenService->addTokenData($this->configuration['token_mime_type'], $mime_type);
    }

    $encoding = $this->configuration['encoding'];
    if ($encoding === '_eca_token') {
      $encoding = $this->getTokenValue('encoding', 'string:base64');
    }
    $encoding_options = explode(':', $encoding);

    foreach ($encoding_options as $option) {
      switch ($option) {

        case 'base64':
          $encoding = 'base64';
          $file_contents = base64_encode($file_contents);
          break;

        case 'raw':
          $encoding = 'raw';
          break;

      }
    }

    foreach ($encoding_options as $option) {
      switch ($option) {

        case 'img':
          $mime_type = $mime_type ?: 'image/jpg';
          $build = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'id' => Html::getUniqueId('eca-embedded-image'),
              'class' => ['eca-embedded-image'],
              'src' => 'data: ' . $mime_type . ';' . ($encoding ?? 'raw') . ',' . $file_contents,
            ],
          ];
          break;

        case 'string':
          $build = [
            '#type' => 'markup',
            '#markup' => $file_contents,
          ];
          break;

      }
    }
  }

}
