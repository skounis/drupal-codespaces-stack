<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Plugin\EntityDiffTrait;

/**
 * Compare 2 entities and return a list of field names that changed.
 *
 * @Action(
 *   id = "eca_diff_entity",
 *   label = @Translation("Entity: compare"),
 *   description = @Translation("Compare 2 entities and return a list of fields that differ"),
 *   eca_version_introduced = "2.0.0",
 *   type = "entity"
 * )
 */
class EntityDiff extends ConfigurableActionBase {

  use EntityDiffTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'return_values' => FALSE,
    ] + $this->commonDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['return_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return values'),
      '#description' => $this->t('If checked, the list will return values. If unchecked, it will only return the machine names of changed fields.'),
      '#default_value' => $this->configuration['return_values'],
    ];
    return $this->doBuildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['return_values'] = $form_state->getValue('return_values');
    $this->doSubmitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $diff = $this->compare($entity);
    if (!$this->configuration['return_values']) {
      $diff = array_keys($diff);
    }
    $this->tokenService->addTokenData($this->configuration['token_name'], $diff);
  }

}
