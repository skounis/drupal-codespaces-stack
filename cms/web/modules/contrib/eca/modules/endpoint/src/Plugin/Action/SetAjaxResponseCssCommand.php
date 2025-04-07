<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Add a css command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_css",
 *   label = @Translation("Ajax Response: css"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseCssCommand extends ResponseAjaxCommandBase {

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
    try {
      $css = $this->yamlParser->parse((string) $this->tokenService->replaceClear($this->configuration['css'])) ?? [];
    }
    catch (ParseException) {
      $this->logger->error('Tried parsing settings in action "@id" as YAML format, but parsing failed.', [
        '@id' => $this->pluginDefinition['id'],
      ]);
      $css = [];
    }
    return new CssCommand($selector, $css);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'selector' => '',
      'css' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('A CSS selector of the element where the CSS should be applied.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSS'),
      '#description' => $this->t('An array of CSS properties and values in YAML format.'),
      '#default_value' => $this->configuration['css'],
      '#weight' => -40,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    $this->configuration['css'] = (string) $form_state->getValue('css');
    parent::submitConfigurationForm($form, $form_state);
  }

}
