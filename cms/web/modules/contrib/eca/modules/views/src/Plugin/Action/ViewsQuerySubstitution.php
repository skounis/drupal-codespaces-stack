<?php

namespace Drupal\eca_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_views\Event\QuerySubstitutions;

/**
 * Substitute a string in a views query.
 *
 * @Action(
 *   id = "eca_views_query_substitution",
 *   label = @Translation("Views: Query Substitution"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class ViewsQuerySubstitution extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->getEvent();
    if (!($event instanceof QuerySubstitutions)) {
      return;
    }
    $from = $this->tokenService->replaceClear($this->configuration['value_from']);
    $to = $this->tokenService->replaceClear($this->configuration['value_to']);
    $event->addSubstitution($from, $to);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    $event = $this->getEvent();
    if ($event instanceof QuerySubstitutions) {
      $result = AccessResult::allowed();
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value_from' => '',
      'value_to' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value to replace'),
      '#default_value' => $this->configuration['value_from'],
      '#required' => TRUE,
      '#weight' => -30,
      '#description' => $this->t('Provide the string that should be replaced in the query.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['value_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Replacement value'),
      '#default_value' => $this->configuration['value_to'],
      '#weight' => -40,
      '#description' => $this->t('Provide the string that should replace the above string in the query.'),
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['value_from'] = $form_state->getValue('value_from');
    $this->configuration['value_to'] = $form_state->getValue('value_to');
    parent::submitConfigurationForm($form, $form_state);
  }

}
