<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\FormFieldPluginTrait;

/**
 * Adds a container element to a form.
 *
 * @Action(
 *   id = "eca_form_add_container_element",
 *   label = @Translation("Form: add container element"),
 *   description = @Translation("Adds a div block element to the form for surrounding child elements."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormAddContainerElement extends FormActionBase {

  use FormFieldPluginTrait;

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

    $name_string = implode('-', $name);
    $container_element = [
      '#type' => 'container',
      '#attributes' => [
        'id' => Html::getUniqueId($name_string),
        'class' => [$name_string],
      ],
      '#optional' => $this->configuration['optional'],
      '#weight' => (int) $this->configuration['weight'],
    ];

    $this->insertFormElement($form, $name, $container_element);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'optional' => FALSE,
      'weight' => '0',
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
    $form['optional'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Optional'),
      '#description' => $this->t('Indicates whether the container should render when it has no visible children.'),
      '#default_value' => $this->configuration['optional'],
      '#weight' => -9,
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('The lower the weight, the element appears before other elements having a higher weight.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -8,
      '#required' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['optional'] = !empty($form_state->getValue('optional'));
    $this->configuration['weight'] = $form_state->getValue('weight');
  }

}
