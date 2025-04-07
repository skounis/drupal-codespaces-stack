<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Insert content to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_insert",
 *   label = @Translation("Ajax Response: insert content"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseInsertCommand extends ResponseAjaxCommandBase {

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
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    $content = $this->configuration['content'];
    if ($this->tokenService->hasTokenData($content)) {
      $content = $this->tokenService->getTokenData($content);
    }
    else {
      $content = $this->tokenService->replaceClear($content);
    }
    if ($content instanceof DataTransferObject) {
      $content = $content->getString();
    }
    try {
      $settings = $this->yamlParser->parse((string) $this->tokenService->replaceClear($this->configuration['settings'])) ?? NULL;
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing settings in action "@id" as YAML format, but parsing failed.', [
        '@id' => $this->pluginDefinition['id'],
      ]);
      $settings = NULL;
    }
    return $this->getInsertCommand($selector, $content, $settings);
  }

  /**
   * Instantiate and return the specific command, overwritten in subclasses.
   *
   * @param string $selector
   *   A CSS selector.
   * @param string|array $content
   *   The content that will be inserted in the matched element(s), either a
   *   render array or an HTML string.
   * @param array|null $settings
   *   An array of JavaScript settings to be passed to any attached behaviors.
   *
   * @return \Drupal\Core\Ajax\CommandInterface
   *   The command.
   */
  protected function getInsertCommand(string $selector, string|array $content, ?array $settings = NULL): CommandInterface {
    return new InsertCommand($selector, $content, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'content' => '',
      'settings' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('A CSS selector of the element where the content will be inserted.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#description' => $this->t('The content to be inserted.'),
      '#default_value' => $this->configuration['content'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Settings'),
      '#description' => $this->t('An array of JavaScript settings in YAML format to be passed to any attached behaviors.'),
      '#default_value' => $this->configuration['settings'],
      '#weight' => -35,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['content'] = (string) $form_state->getValue('content');
    $this->configuration['settings'] = (string) $form_state->getValue('settings');
    parent::submitConfigurationForm($form, $form_state);
  }

}
