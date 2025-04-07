<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Plugin implementation of the ECA condition for entity field is accessible.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_is_accessible",
 *   label = @Translation("Entity: field is accessible"),
 *   description = @Translation("Evaluates whether the current user has operational access on an entity field."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldIsAccessible extends ConditionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if (!($entity instanceof FieldableEntityInterface)) {
      return FALSE;
    }
    $field_name = trim((string) $this->tokenService->replaceClear($this->configuration['field_name'] ?? ''));
    if (($field_name === '') || !($entity->hasField($field_name))) {
      return FALSE;
    }
    $field_op = $this->configuration['operation'];
    if ($field_op === '_eca_token') {
      $field_op = $this->getTokenValue('operation', 'view');
    }
    $entity_op = $field_op === 'edit' ? 'update' : $field_op;
    return $this->negationCheck($entity->access($entity_op) && $entity->$field_name->access($field_op));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'operation' => 'view',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field machine name'),
      '#description' => $this->t('The machine name of the field to check.'),
      '#default_value' => $this->configuration['field_name'] ?? '',
      '#required' => TRUE,
      '#weight' => -20,
    ];
    $form['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#description' => $this->t('The operation, like view, edit or delete to check accessibility.'),
      '#options' => [
        'view' => $this->t('View'),
        'edit' => $this->t('Edit'),
        'delete' => $this->t('Delete'),
      ],
      '#default_value' => $this->configuration['operation'] ?? 'view',
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['operation'] = $form_state->getValue('operation');
    parent::submitConfigurationForm($form, $form_state);
  }

}
