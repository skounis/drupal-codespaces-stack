<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Set the response headers.
 *
 * @Action(
 *   id = "eca_endpoint_set_response_headers",
 *   label = @Translation("Response: set headers"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class SetResponseHeaders extends ResponseActionBase {

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
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(): void {
    $headers = $this->configuration['headers'];

    if ($this->configuration['use_yaml']) {
      try {
        $headers = $this->yamlParser->parse($headers);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a Token value in action "eca_endpoint_set_response_headers" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      // Allow direct assignment of available data from the Token environment.
      $headers = $this->tokenService->getOrReplace($headers);
      if ($headers instanceof DataTransferObject) {
        $headers = $headers->toArray();
      }
      elseif ($headers instanceof TypedDataInterface) {
        $headers = $headers->getValue();
      }
    }

    $response_headers = $this->getResponse()->headers;
    if (is_iterable($headers)) {
      foreach ($headers as $k => $v) {
        $response_headers->set($k, $v);
      }
    }
    else {
      throw new \InvalidArgumentException("Cannot use a non-iterable data value for setting response headers. Data must be resolvable to key-value pairs.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'headers' => '',
      'use_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Headers'),
      '#description' => $this->t('The headers to set. If you can also use YAML syntax by enabling it below.'),
      '#default_value' => $this->configuration['headers'],
      '#weight' => -20,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above value as YAML format'),
      '#description' => $this->t('Nested data can be set using YAML format, for example <em>Content-Type: "text/html; charset=UTF-8"</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>Content-Type: "[content_type]"</em>'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -10,
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['headers'] = $form_state->getValue('headers');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

}
