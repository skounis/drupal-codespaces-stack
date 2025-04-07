<?php

namespace Drupal\eca_workflow\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform a workflow transition on an entity.
 *
 * @Action(
 *   id = "eca_workflow_transition",
 *   type = "entity",
 *   deriver = "Drupal\eca_workflow\Plugin\Action\WorkflowTransitionDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class WorkflowTransition extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected ModerationInformationInterface $moderationInformation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setModerationInformation($container->get('content_moderation.moderation_information'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->createRevision($entity, $entity->isDefaultRevision());
    $new_state = $this->configuration['new_state'];
    if ($new_state === '_eca_token') {
      $new_state = $this->getTokenValue('new_state', '');
    }
    $entity->set('moderation_state', $new_state);
    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->currentUser->id());

      $log = $this->tokenService->replace($this->configuration['revision_log']);
      if ($log instanceof DataTransferObject) {
        $log = $log->getString();
      }
      if ($log) {
        $entity->setRevisionLogMessage($log);
      }
    }
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    if (($object instanceof ContentEntityInterface) && ($workflow = $this->moderationInformation->getWorkflowForEntity($object))) {
      $current_state = $object->get('moderation_state')->value;
      $workflowPlugin = $workflow->getTypePlugin();
      $new_state = $this->configuration['new_state'];
      if ($new_state === '_eca_token') {
        $new_state = $this->getTokenValue('new_state', '');
      }
      if ($workflowPlugin->hasState($current_state) && $workflowPlugin->getState($current_state)->canTransitionTo($new_state)) {
        $result = AccessResult::allowed();
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'new_state' => '',
      'revision_log' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = Workflow::load($this->getPluginDefinition()['workflow_id']);
    foreach ($workflow->getTypePlugin()->getStates() as $state) {
      $options[$state->id()] = $state->label();
    }
    $form['new_state'] = [
      '#type' => 'select',
      '#title' => $this->t('New state'),
      '#options' => $options,
      '#default_value' => $this->configuration['new_state'],
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['revision_log'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Revision Log'),
      '#default_value' => $this->configuration['revision_log'],
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['new_state'] = $form_state->getValue('new_state');
    $this->configuration['revision_log'] = $form_state->getValue('revision_log');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the moderation information service.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function setModerationInformation(ModerationInformationInterface $moderation_information): void {
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [
      'config' => [
        'workflows.workflow.' . $this->pluginDefinition['workflow_id'],
      ],
    ];
  }

}
