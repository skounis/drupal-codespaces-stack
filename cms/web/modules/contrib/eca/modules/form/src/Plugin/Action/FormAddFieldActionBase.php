<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Plugin\FormFieldPluginTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for actions adding a field element to a form.
 */
abstract class FormAddFieldActionBase extends FormActionBase {

  use FormFieldPluginTrait;
  use PluginFormTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form = &$this->getCurrentForm())) {
      return;
    }
    $name = trim((string) $this->tokenService->replace($this->configuration['name']));
    if ($name === '') {
      throw new \InvalidArgumentException('Cannot use an empty string as field name.');
    }
    $this->configuration['field_name'] = $name;
    $name = $this->getFieldNameAsArray();
    $field_element = $this->buildFieldElement();
    if (count($name) > 1) {
      $field_element['#parents'] = $name;
    }
    $this->insertFormElement($form, $name, $field_element);
  }

  /**
   * Builds up the field element using the plugin configuration.
   *
   * @return array
   *   The built field element.
   */
  protected function buildFieldElement(): array {
    $type = $this->configuration['type'];
    if ($type === '_eca_token') {
      $type = $this->getTokenValue('type', '');
    }
    $field_element = [
      '#type' => $type,
      '#title' => $this->tokenService->replaceClear($this->configuration['title']),
      '#required' => $this->configuration['required'],
      '#weight' => (int) $this->configuration['weight'],
    ];
    if ($this->configuration['description'] !== '') {
      $field_element['#description'] = $this->tokenService->replaceClear($this->configuration['description']);
    }
    if (trim((string) $this->configuration['default_value']) !== '') {
      $field_element['#default_value'] = $this->buildDefaultValue();
    }
    return $field_element;
  }

  /**
   * Get a list of available field element types.
   *
   * @return array
   *   The available types, keyed by machine name and values are labels.
   */
  abstract protected function getTypeOptions(): array;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'type' => '',
      'name' => '',
      'title' => '',
      'description' => '',
      'required' => FALSE,
      'weight' => '0',
      'default_value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $type_options = $this->getTypeOptions();
    if (count($type_options) > 1) {
      $form['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Field type'),
        '#description' => $this->t('List of the available types of the field to be added.'),
        '#weight' => -100,
        '#options' => $type_options,
        '#default_value' => $this->configuration['type'],
        '#required' => TRUE,
        '#eca_token_select_option' => TRUE,
      ];
    }
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The field name is a machine name and is used for being identified on form submission. Example: <em>first_name</em>. It can later be accessed via token <em>[current_form:values:first_name]</em>.'),
      '#weight' => -90,
      '#default_value' => $this->configuration['name'],
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field title'),
      '#description' => $this->t('The title of the field to be added.'),
      '#weight' => -80,
      '#default_value' => $this->configuration['title'],
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field description'),
      '#description' => $this->t('The description of the field to be added.'),
      '#weight' => -70,
      '#default_value' => $this->configuration['description'],
    ];
    $form['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Field is required'),
      '#description' => $this->t('Whether the added field is required or not.'),
      '#default_value' => $this->configuration['required'],
      '#weight' => -60,
    ];
    $form['default_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default value'),
      '#description' => $this->t('The default value if the field is empty.'),
      '#weight' => -30,
      '#default_value' => $this->configuration['default_value'],
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('The lower the weight, the submit action appears before other submit actions having a higher weight.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -20,
      '#required' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $default = $this->defaultConfiguration();
    $this->configuration['type'] = $form_state->hasValue('type') ? $form_state->getValue('type') : $default['type'];
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['required'] = !empty($form_state->getValue('required'));
    $this->configuration['weight'] = $form_state->getValue('weight');
    $this->configuration['default_value'] = $form_state->getValue('default_value');
  }

  /**
   * Builds up the default value for the form element.
   *
   * @return array|string|\Drupal\Component\Render\MarkupInterface
   *   The default value.
   */
  protected function buildDefaultValue(): array|string|MarkupInterface {
    return $this->tokenService->replaceClear($this->configuration['default_value']);
  }

}
