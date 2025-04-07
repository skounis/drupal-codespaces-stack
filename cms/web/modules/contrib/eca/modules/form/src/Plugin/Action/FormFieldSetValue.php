<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormFieldPluginTrait;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Set submitted input of a form field.
 *
 * @Action(
 *   id = "eca_form_field_set_value",
 *   label = @Translation("Form field: set submitted value"),
 *   description = @Translation("Set or overwrite the submitted input value of a form field."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldSetValue extends ConfigurableActionBase {

  use FormFieldPluginTrait;

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
  public function defaultConfiguration(): array {
    return [
      'field_value' => '',
      'use_yaml' => FALSE,
    ] + $this->defaultFormFieldConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field value'),
      '#default_value' => $this->configuration['field_value'],
      '#weight' => -45,
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above value as YAML format'),
      '#description' => $this->t('Nested data can be set using YAML format, for example <em>mykey: "My value"</em>. When using this format, this options needs to be enabled.'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -43,
    ];
    $form = $this->buildFormFieldConfigurationForm($form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateFormFieldConfigurationForm($form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_value'] = $form_state->getValue('field_value');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->submitFormFieldConfigurationForm($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form_state = $this->getCurrentFormState())) {
      return;
    }

    $value = $this->configuration['field_value'];
    if ($this->configuration['use_yaml']) {
      try {
        $value = $this->yamlParser->parse($value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing field value in action "eca_form_field_set_value" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $value = (string) $this->tokenService->replaceClear($value);
    }
    $this->filterFormFieldValue($value);

    $original_field_name = $this->configuration['field_name'];
    $this->configuration['field_name'] = (string) $this->tokenService->replace($original_field_name);

    $found = FALSE;
    $existing_value = &$this->getSubmittedValue($found);
    if ($found) {
      $existing_value = $value;
    }
    else {
      $values = &$form_state->getValues();
      if (!$values) {
        $values = &$form_state->getUserInput();
        if (!$values) {
          // Back to form state's values.
          $values = &$form_state->getValues();
        }
      }
      NestedArray::setValue($values, $this->getFieldNameAsArray(), $value, TRUE);
    }

    // Restoring the original config entry.
    $this->configuration['field_name'] = $original_field_name;
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
