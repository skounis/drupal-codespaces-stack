<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Render\ElementInfoManager;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\FormFieldPluginTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add a grouping element to a form.
 *
 * @Action(
 *   id = "eca_form_add_group_element",
 *   label = @Translation("Form: add grouping element"),
 *   description = @Translation("Add a collapsible details element (also known as fieldset) for grouping form fields."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddGroupElement extends FormActionBase {

  use FormFieldPluginTrait;

  /**
   * The element info plugin manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManager
   */
  protected ElementInfoManager $elementInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // Reverse the order of lookups.
    $instance->lookupKeys = ['array_parents', 'parents'];
    $instance->elementInfo = $container->get('plugin.manager.element_info');
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
      throw new \InvalidArgumentException('Cannot use an empty string as element name');
    }
    $this->configuration['field_name'] = $name;
    $name = $this->getFieldNameAsArray();

    $group_element = [
      '#type' => 'details',
      '#value' => $this->tokenService->replaceClear($this->configuration['summary_value']),
      '#title' => $this->tokenService->replaceClear($this->configuration['title']),
      '#weight' => (int) $this->configuration['weight'],
      '#open' => $this->configuration['open'],
    ];

    if ($this->configuration['group'] !== '') {
      $group = (string) $this->tokenService->replaceClear($this->configuration['group']);
      if ($group !== '') {
        $group_element['#group'] = $group;
      }
    }

    if ($this->configuration['introduction_text'] !== '') {
      $introduction_text = (string) $this->tokenService->replaceClear($this->configuration['introduction_text']);
      if ($introduction_text !== '') {
        $group_element['introduction_text'] = [
          '#type' => 'markup',
          '#prefix' => '<div class="introduction-text">',
          '#markup' => $introduction_text,
          '#suffix' => '</div>',
          '#weight' => -1000,
        ];
      }
    }
    if ($this->configuration['summary_value'] !== '') {
      $summary_value = (string) $this->tokenService->replaceClear($this->configuration['summary_value']);
      if ($summary_value !== '') {
        $group_element['#value'] = $summary_value;
      }
    }

    $this->insertFormElement($form, $name, $group_element);

    if ($fields = (string) $this->tokenService->replace($this->configuration['fields'])) {
      $name_string = implode('][', $name);
      foreach (DataTransferObject::buildArrayFromUserInput($fields) as $field) {
        $this->configuration['field_name'] = $field;
        if ($field_element = &$this->getTargetElement()) {
          $field_element['#group'] = $name_string;

          // @todo Remove this workaround once #2190333 got fixed.
          if (empty($field_element['#process']) && empty($field_element['#pre_render']) && isset($field_element['#type'])) {
            $type = $field_element['#type'];
            if ($this->elementInfo->hasDefinition($type)) {
              $field_element += $this->elementInfo->getInfo($type);
            }
          }
          $needs_process_callbacks = TRUE;
          if (!empty($field_element['#process'])) {
            foreach ($field_element['#process'] as $process_callback) {
              if (is_array($process_callback) && end($process_callback) === 'processGroup') {
                $needs_process_callbacks = FALSE;
                break;
              }
            }
          }
          if ($needs_process_callbacks) {
            $field_element['#pre_render'][] = [
              RenderElementBase::class,
              'preRenderGroup',
            ];
            $field_element['#process'][] = [
              RenderElementBase::class,
              'processGroup',
            ];
          }

        }
        unset($field_element);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'title' => '',
      'open' => FALSE,
      'weight' => '0',
      'fields' => '',
      'introduction_text' => '',
      'summary_value' => '',
      'group' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element name'),
      '#description' => $this->t('The element name is a machine name and is used for being identified when rendering the form. Example: <em>name_info</em>'),
      '#weight' => -10,
      '#default_value' => $this->configuration['name'],
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This will be shown to the user in the form as grouping title.'),
      '#weight' => -9,
      '#default_value' => $this->configuration['title'],
      '#required' => TRUE,
    ];
    $form['open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open'),
      '#default_value' => $this->configuration['open'],
      '#description' => $this->t('Whether the group should be open to edit or collapsed when displayed.'),
      '#weight' => -8,
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('The lower the weight, the element appears before other elements having a higher weight.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -7,
      '#required' => TRUE,
    ];
    $form['fields'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fields'),
      '#description' => $this->t('Machine names of form fields that should be grouped together. Define multiple values separated with commas. Example: <em>first_name,last_name</em>'),
      '#weight' => -6,
      '#default_value' => $this->configuration['fields'],
    ];
    $form['introduction_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#description' => $this->t('Here you can set an introduction text of the group.'),
      '#weight' => -5,
      '#default_value' => $this->configuration['introduction_text'],
    ];
    $form['summary_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summary value'),
      '#description' => $this->t('Here you can set the summary text of the group.'),
      '#weight' => -4,
      '#default_value' => $this->configuration['summary_value'],
    ];
    $form['group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group'),
      '#description' => $this->t('Here you can set this element to be a part of a parent group.'),
      '#weight' => -4,
      '#default_value' => $this->configuration['group'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['open'] = !empty($form_state->getValue('open'));
    $this->configuration['weight'] = $form_state->getValue('weight');
    $this->configuration['fields'] = $form_state->getValue('fields');
    $this->configuration['introduction_text'] = $form_state->getValue('introduction_text');
    $this->configuration['summary_value'] = $form_state->getValue('summary_value');
    $this->configuration['group'] = $form_state->getValue('group');
  }

}
