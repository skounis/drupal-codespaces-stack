<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Flag the entity for creating a new revision.
 *
 * @Action(
 *   id = "eca_set_new_revision",
 *   label = @Translation("Entity: set new revision"),
 *   description = @Translation("Flags the entity so that a new revision will be created on the next save."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class SetNewRevision extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'new_revision' => TRUE,
      'revision_log' => '',
      'revision_uid' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['new_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => $this->configuration['new_revision'],
      '#description' => $this->t('Whether to create a new revision or not'),
    ];
    $form['revision_log'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Revision Log'),
      '#default_value' => $this->configuration['revision_log'],
      '#description' => $this->t('The optional revision log message.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['revision_uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Revision user'),
      '#default_value' => $this->configuration['revision_uid'],
      '#description' => $this->t('The optional revision user. Leave empty to set the current user.'),
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['new_revision'] = !empty($form_state->getValue('new_revision'));
    $this->configuration['revision_log'] = $form_state->getValue('revision_log');
    $this->configuration['revision_uid'] = $form_state->getValue('revision_uid');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!($object instanceof AccessibleInterface)) {
      $result = AccessResult::forbidden();
      return $return_as_object ? $result : $result->isAllowed();
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $object;
    $entity_op = 'update';

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $entity->access($entity_op, $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof RevisionableInterface)) {
      return;
    }
    $new_revision = $this->configuration['new_revision'];
    $entity->setNewRevision($new_revision);
    if ($new_revision && $entity instanceof RevisionLogInterface) {
      $uid = $this->tokenService->replace($this->configuration['revision_uid']);
      if ($uid instanceof AccountInterface) {
        $uid = $uid->id();
      }
      elseif (is_numeric($uid)) {
        $uid = (int) $uid;
      }
      else {
        $uid = $this->currentUser->id();
      }
      $entity->setRevisionUserId($uid);
      $log = $this->tokenService->replace($this->configuration['revision_log']);
      if ($log instanceof DataTransferObject) {
        $log = $log->getString();
      }
      if ($log) {
        $entity->setRevisionLogMessage($log);
      }
      $entity->setRevisionCreationTime($this->time->getRequestTime());
    }
  }

}
