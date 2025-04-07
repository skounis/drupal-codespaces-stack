<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Event\ContentEntityViewModeAlter;

/**
 * Flag the entity for creating a new revision.
 *
 * @Action(
 *   id = "eca_set_view_mode",
 *   label = @Translation("Entity: set view mode"),
 *   description = @Translation("Changes the view mode of the entity. Only work after the event 'Alter entity view mode'."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetViewMode extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'new_view_mode' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['new_view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['new_view_mode'],
      '#description' => $this->t('The machine name of the view mode.'),
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['new_view_mode'] = $form_state->getValue('new_view_mode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->getEvent() instanceof ContentEntityViewModeAlter) {
      $result = AccessResult::allowed();
      $viewMode = $this->tokenService->replaceClear($this->configuration['new_view_mode']);
      if ($viewMode === '') {
        $result = AccessResult::forbidden();
      }
    }
    else {
      $result = AccessResult::forbidden();
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $viewMode = $this->tokenService->replaceClear($this->configuration['new_view_mode']);
    /** @var \Drupal\eca_content\Event\ContentEntityViewModeAlter $event */
    $event = $this->getEvent();
    $event->setViewMode($viewMode);
  }

}
