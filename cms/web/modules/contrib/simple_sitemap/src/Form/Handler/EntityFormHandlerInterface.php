<?php

namespace Drupal\simple_sitemap\Form\Handler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for altering an entity form.
 */
interface EntityFormHandlerInterface extends ContainerInjectionInterface {

  /**
   * Alters the entity form to provide sitemap settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see simple_sitemap_form_alter()
   * @see simple_sitemap_engines_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state);

  /**
   * Returns a form to configure the sitemap settings.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   *
   * @return array
   *   The form elements for the sitemap settings.
   */
  public function settingsForm(array $form): array;

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state);

  /**
   * Sets the form entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   *
   * @return $this
   */
  public function setEntity(EntityInterface $entity);

  /**
   * Gets the entity type ID.
   *
   * @return string|null
   *   The entity type ID if available, or NULL otherwise.
   */
  public function getEntityTypeId(): ?string;

  /**
   * Gets the bundle name.
   *
   * @return string|null
   *   The bundle name if available, or NULL otherwise.
   */
  public function getBundleName(): ?string;

  /**
   * Determines whether the specified form operation is supported.
   *
   * @param string $operation
   *   The name of the operation.
   *
   * @return bool
   *   TRUE if the form operation is supported, FALSE otherwise.
   */
  public function isSupportedOperation(string $operation): bool;

}
