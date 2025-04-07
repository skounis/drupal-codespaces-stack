<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Add open dialog command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_open_dialog",
 *   label = @Translation("Ajax Response: open dialog"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseOpenDialogCommand extends ResponseAjaxCommandBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector'] ?? '');
    $title = (string) $this->tokenService->replaceClear($this->configuration['title']);
    $content = $this->configuration['content'];
    if ($this->tokenService->hasTokenData($content)) {
      $content = $this->tokenService->getTokenData($content)->toArray();
    }
    else {
      $content = $this->tokenService->replaceClear($content);
    }
    try {
      $options = $this->yamlParser->parse((string) $this->tokenService->replaceClear($this->configuration['options'])) ?? [];
      $settingsString = (string) $this->tokenService->replaceClear($this->configuration['settings']);
      $settings = $settingsString === '' ?
        NULL :
        $this->yamlParser->parse($settingsString);
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing options and settings in action "@id" as YAML format, but parsing failed.', [
        '@id' => $this->pluginDefinition['id'],
      ]);
      $options = [];
      $settings = NULL;
    }
    return $this->getDialogCommand($selector, $title, $content, $options, $settings);
  }

  /**
   * Instantiate and return the specific command, overwritten in subclass.
   *
   * @param string $selector
   *   The selector.
   * @param string $title
   *   The dialog title.
   * @param string|array $content
   *   The dialog content.
   * @param array $options
   *   The dialog options.
   * @param array|null $settings
   *   The settings.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   The command.
   */
  protected function getDialogCommand(string $selector, string $title, string|array $content, array $options, ?array $settings): CommandInterface {
    return new OpenDialogCommand($selector, $title, $content, $options, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'title' => '',
      'content' => '',
      'options' => '',
      'settings' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (isset($this->configuration['selector'])) {
      $form['selector'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSS Selector'),
        '#description' => $this->t('The selector of the dialog.'),
        '#default_value' => $this->configuration['selector'],
        '#weight' => -45,
        '#required' => TRUE,
        '#eca_token_replacement' => TRUE,
      ];
    }
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The title of the dialog.'),
      '#default_value' => $this->configuration['title'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#description' => $this->t('The content to be inserted.'),
      '#default_value' => $this->configuration['content'],
      '#weight' => -35,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Dialog options'),
      '#description' => $this->t('Options in YAML format to be passed to the dialog implementation. Any jQuery UI option can be used. See https://api.jqueryui.com/dialog.'),
      '#default_value' => $this->configuration['options'],
      '#weight' => -30,
      '#eca_token_replacement' => TRUE,
    ];
    $form['settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Settings'),
      '#description' => $this->t('Custom settings in YAML format that will be passed to the Drupal behaviors on the content of the dialog. If left empty, the settings will be populated automatically from the current request.'),
      '#default_value' => $this->configuration['settings'],
      '#weight' => -25,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (isset($this->defaultConfiguration()['selector'])) {
      $this->configuration['selector'] = (string) $form_state->getValue('selector');
    }
    $this->configuration['title'] = (string) $form_state->getValue('title');
    $this->configuration['content'] = (string) $form_state->getValue('content');
    $this->configuration['options'] = (string) $form_state->getValue('options');
    $this->configuration['settings'] = (string) $form_state->getValue('settings');
    parent::submitConfigurationForm($form, $form_state);
  }

}
