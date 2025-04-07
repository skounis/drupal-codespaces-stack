<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * View a specified entity.
 *
 * @Action(
 *   id = "eca_render_entity_view",
 *   label = @Translation("Render: view entity"),
 *   description = @Translation("View a specified entity."),
 *   eca_version_introduced = "1.1.0",
 *   type = "entity"
 * )
 */
class EntityView extends RenderElementActionBase {

  /**
   * The current entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'view_mode' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['view_mode'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
      '#description' => $this->t('Example: <em>default, teaser</em>'),
      '#required' => TRUE,
      '#weight' => -30,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = parent::access($object, $account, TRUE);
    if ($access_result->isAllowed()) {
      if (!($object instanceof EntityInterface)) {
        $access_result = AccessResult::forbidden("The given object is not an entity.");
      }
      else {
        $account = $account ?? $this->currentUser;
        $access_result = $object->access('view', $account, TRUE);
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof EntityInterface)) {
      return;
    }
    $this->entity = $entity;
    parent::execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    if ($this->entity instanceof EntityInterface) {
      $view_mode = trim((string) $this->tokenService->replaceClear($this->configuration['view_mode']));
      if ($view_mode === '') {
        throw new \InvalidArgumentException("No view mode specified.");
      }
      $build = $this->entityTypeManager
        ->getViewBuilder($this->entity->getEntityTypeId())
        ->view($this->entity, $view_mode);
    }
    else {
      throw new \InvalidArgumentException("No entity given for building the entity view.");
    }
  }

}
