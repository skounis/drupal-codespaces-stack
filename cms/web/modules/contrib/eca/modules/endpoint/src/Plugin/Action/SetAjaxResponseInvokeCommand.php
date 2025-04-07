<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Add an invoke command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_invoke",
 *   label = @Translation("Ajax Response: invoke"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseInvokeCommand extends ResponseAjaxCommandBase {

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
    $method = (string) $this->tokenService->replaceClear($this->configuration['method']);
    try {
      $arguments = $this->yamlParser->parse((string) $this->tokenService->replaceClear($this->configuration['arguments'])) ?? [];
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing settings in action "@id" as YAML format, but parsing failed.', [
        '@id' => $this->pluginDefinition['id'],
      ]);
      $arguments = [];
    }
    return new InvokeCommand($selector, $method, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'method' => '',
      'arguments' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('A jQuery selector.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['method'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Method'),
      '#description' => $this->t('The name of a jQuery method to invoke.'),
      '#default_value' => $this->configuration['method'],
      '#weight' => -40,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['arguments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Arguments'),
      '#description' => $this->t('An array of arguments in YAML format that will be passed to the method.'),
      '#default_value' => $this->configuration['arguments'],
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
    $this->configuration['method'] = (string) $form_state->getValue('method');
    $this->configuration['arguments'] = (string) $form_state->getValue('arguments');
    parent::submitConfigurationForm($form, $form_state);
  }

}
