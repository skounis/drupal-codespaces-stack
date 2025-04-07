<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build an entity form.
 *
 * @Action(
 *   id = "eca_render_entity_form",
 *   label = @Translation("Render: entity form"),
 *   description = @Translation("Build an entity form using a specified entity."),
 *   eca_version_introduced = "1.1.0",
 *   type = "entity"
 * )
 */
class EntityForm extends RenderElementActionBase {

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $formBuilder;

  /**
   * The current entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->formBuilder = $container->get('entity.form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'operation' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['operation'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Operation'),
      '#default_value' => $this->configuration['operation'],
      '#description' => $this->t('Example: <em>default, save, delete</em>'),
      '#required' => TRUE,
      '#weight' => -30,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operation'] = $form_state->getValue('operation');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = parent::access($object, $account, TRUE);
    if ($access_result->isAllowed()) {
      $account = $account ?? $this->currentUser;
      if (!($object instanceof EntityInterface)) {
        $access_result = AccessResult::forbidden("The given object is not an entity.");
      }
      elseif ($object->isNew()) {
        $access_result = $this->entityTypeManager
          ->getAccessControlHandler($object->getEntityTypeId())
          ->createAccess($object->bundle(), $account, [], TRUE);
      }
      else {
        $op = $this->configuration['operation'] === 'delete' ? 'delete' : 'update';
        $access_result = $this->entityTypeManager
          ->getAccessControlHandler($object->getEntityTypeId())
          ->access($object, $op, $account, TRUE);
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
      $operation = trim((string) $this->tokenService->replaceClear($this->configuration['operation']));
      if ($operation === '') {
        throw new \InvalidArgumentException("No form operation specified.");
      }
      $build = $this->formBuilder->getForm($this->entity, $operation);
    }
    else {
      throw new \InvalidArgumentException("No entity given for building the entity form.");
    }
  }

}
