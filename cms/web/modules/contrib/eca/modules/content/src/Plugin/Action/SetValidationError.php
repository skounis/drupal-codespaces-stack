<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Event\ContentEntityValidate;

/**
 * Validates a content entity.
 *
 * @Action(
 *   id = "eca_content_validation_error",
 *   label = @Translation("Entity: Set validation error"),
 *   description = @Translation("Only works when reacting upon <em>Validate content entity</em> events."),
 *   eca_version_introduced = "2.1.x",
 *   type = "entity"
 * )
 */
class SetValidationError extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
      'property' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error message'),
      '#default_value' => $this->configuration['message'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
      '#weight' => -20,
    ];
    $form['property'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property path'),
      '#default_value' => $this->configuration['property'],
      '#description' => $this->t('The optional property path on the entity, where to set the validation error. This may be the machine name of a field (e.g. <em>body</em>) or a property of a field. Example: <em>body.0.value</em>'),
      '#eca_token_replacement' => TRUE,
      '#required' => FALSE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['property'] = $form_state->getValue('property');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof ContentEntityValidate ? AccessResult::allowed() : AccessResult::forbidden("Event is not compatible with this action.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }
    if (!($this->event instanceof ContentEntityValidate)) {
      return;
    }

    $message = $this->tokenService->replace($this->configuration['message']);
    $property_path = trim((string) $this->tokenService->replace($this->configuration['property']));

    $violation = $this->event
      ->getValidator()
      ->getContext()
      ->buildViolation($message);

    if ($property_path !== '') {
      $violation->atPath($property_path);
    }

    $violation->addViolation();
  }

}
