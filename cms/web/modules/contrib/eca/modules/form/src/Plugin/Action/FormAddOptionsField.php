<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add a field with options to a form.
 *
 * @Action(
 *   id = "eca_form_add_optionsfield",
 *   label = @Translation("Form: add options field"),
 *   description = @Translation("Add a field with options as radios, checkboxes or select dropdown to the current form in scope."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddOptionsField extends FormAddFieldActionBase {

  use FormFieldSetOptionsTrait {
    defaultConfiguration as setOptionsDefaultConfiguration;
    buildConfigurationForm as setOptionsBuildConfigurationForm;
    submitConfigurationForm as setOptionsSubmitConfigurationForm;
    execute as setOptionsExecute;
  }

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
  protected function buildFieldElement(): array {
    $element = parent::buildFieldElement();
    // Options will be filled up within ::execute().
    $element['#options'] = [];
    $is_multiple = (bool) $this->configuration['multiple'];
    $element['#multiple'] = $is_multiple;
    if (!$is_multiple && $element['#type'] === 'checkboxes') {
      $element['#type'] = 'radios';
    }
    elseif ($is_multiple && $element['#type'] === 'radios') {
      $element['#type'] = 'checkboxes';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'type' => 'select',
      'multiple' => TRUE,
    ] + $this->setOptionsDefaultConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTypeOptions(): array {
    $type_options = [
      'checkboxes' => $this->t('Checkboxes / radio buttons'),
      'select' => $this->t('Dropdown selection'),
    ];
    if ($this->moduleHandler->moduleExists('select2')) {
      $type_options['select2'] = $this->t('Select2 dropdown');
    }
    return $type_options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple values'),
      '#description' => $this->t('Whether the user can select more than one value in the option field.'),
      '#default_value' => $this->configuration['multiple'],
      '#weight' => -45,
    ];
    return $this->setOptionsBuildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['multiple'] = !empty($form_state->getValue('multiple'));
    $this->setOptionsSubmitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    parent::execute();
    $this->setOptionsExecute();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if ($this->configuration['type'] === 'select2') {
      $dependencies['module'][] = 'select2';
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDefaultValue(): array|string|MarkupInterface {
    if ($default_options = $this->buildOptionsArray($this->configuration['default_value'])) {
      $is_multiple = (bool) $this->configuration['multiple'];
      return $is_multiple ? array_values($default_options) : key($default_options);
    }
    return parent::buildDefaultValue();
  }

}
