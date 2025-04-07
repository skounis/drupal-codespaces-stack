<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Set a property value on the form state.
 *
 * @Action(
 *   id = "eca_form_state_set_property_value",
 *   label = @Translation("Form state: set property value"),
 *   description = @Translation("Sets a property value on the current form state in scope, which can be used on validation and submission."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormStateSetPropertyValue extends FormStatePropertyActionBase {

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
  public function execute(): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }
    $token = $this->tokenService;

    $property_name = $this->normalizePropertyPath($token->replace($this->configuration['property_name']));
    if (empty($property_name)) {
      return;
    }
    $name = explode('.', $property_name);
    // Enforce the first level name to be "eca" in order to not interfere with
    // other form state properties.
    $name = array_merge(['eca'], $name);

    $value = $this->configuration['property_value'];
    if ($this->configuration['use_yaml']) {
      try {
        $value = $this->yamlParser->parse($value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a property value in action "eca_form_state_set_property_value" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $value = (string) $token->replaceClear($value);
    }

    $form_state->set($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'property_value' => '',
      'use_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['property_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value of the property'),
      '#description' => $this->t('The value of the property to be set.'),
      '#default_value' => $this->configuration['property_value'],
      '#weight' => -49,
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above value as YAML format'),
      '#description' => $this->t('Nested data can be set using YAML format, for example <em>mykey: "My value"</em>. When using this format, this options needs to be enabled.'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -48,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['property_value'] = $form_state->getValue('property_value');
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
