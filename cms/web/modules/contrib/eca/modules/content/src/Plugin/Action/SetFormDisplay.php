<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Set a specific form display.
 *
 * @Action(
 *   id = "eca_content_set_form_display",
 *   label = @Translation("Entity: set form display"),
 *   description = @Translation("Change to a specific form display mode. Only works when reacting upon the event <em>Prepare content entity form</em>."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class SetFormDisplay extends ConfigurableActionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;
    $result = parent::access($object, $account, TRUE);
    $result = $result->andIf(AccessResult::allowedIf(!is_null($this->getFormDisplay())));
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($form_display = $this->getFormDisplay())) {
      return;
    }
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof ContentEntityFormInterface)) {
      return;
    }

    $current_id = $form_object->getFormDisplay($form_state)->id();
    if ($current_id === $form_display->id()) {
      return;
    }

    $form_object->setFormDisplay($form_display, $form_state);
  }

  /**
   * Get the targeted entity form display.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   *   The entity form display, or NULL if not available.
   */
  protected function getFormDisplay(): ?EntityFormDisplayInterface {
    if (!($form_state = $this->getCurrentFormState())) {
      return NULL;
    }
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof ContentEntityFormInterface)) {
      return NULL;
    }
    $display_mode = trim((string) $this->tokenService->replaceClear($this->configuration['display_mode'] ?? 'default'));
    if ($display_mode === '') {
      return NULL;
    }

    $entity = $form_object->getEntity();
    if ($entity instanceof FieldableEntityInterface) {
      $display = EntityFormDisplay::collectRenderDisplay($entity, $display_mode, $display_mode === 'default');
      if ($display->isNew()) {
        return NULL;
      }
      return $display;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'display_mode' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['display_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form display mode'),
      '#description' => $this->t('The machine name of the display mode. Please note: This action only works when reacting upon the event "Prepare content entity form".'),
      '#default_value' => $this->configuration['display_mode'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['display_mode'] = $form_state->getValue('display_mode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $display_mode = trim((string) $this->tokenService->replaceClear($this->configuration['display_mode'] ?? 'default'));
    if ($display_mode !== '') {
      foreach (EntityFormDisplay::loadMultiple() as $display) {
        if ($display->get('mode') === $display_mode) {
          $dependencies[$display->getConfigDependencyKey()][] = $display->getConfigDependencyName();
        }
      }
    }
    return $dependencies;
  }

}
