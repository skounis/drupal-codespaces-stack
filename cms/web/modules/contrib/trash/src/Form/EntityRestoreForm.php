<?php

declare(strict_types=1);

namespace Drupal\trash\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Url;
use Drupal\trash\TrashManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic base class for a content entity restore form.
 */
class EntityRestoreForm extends ContentEntityConfirmFormBase implements WorkspaceSafeFormInterface {

  /**
   * The trash manager.
   */
  protected TrashManager $trashManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->trashManager = $container->get('trash.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to restore the @entity-type %label?', [
      '@entity-type' => $this->getEntity()->getEntityType()->getSingularLabel(),
      '%label' => $this->getEntity()->label() ?? $this->getEntity()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('trash.admin_content_trash_entity_type', [
      'entity_type_id' => $this->getEntity()->getEntityTypeId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $this->trashManager->getHandler($this->getEntity()->getEntityTypeId())?->restoreFormAlter($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $message = $this->t('The @entity-type %label has been restored from trash.', [
      '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label() ?? $entity->id(),
    ]);

    trash_restore_entity($entity);
    $form_state->setRedirectUrl($this->getRedirectUrl());

    $this->messenger()->addStatus($message);
    $this->getLogger('trash')->info('@entity-type (@bundle): restored %label.', [
      '@entity-type' => $entity->getEntityType()->getLabel(),
      '@bundle' => $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId())[$entity->bundle()]['label'],
      '%label' => $entity->label() ?? $entity->id(),
    ]);
  }

  /**
   * Returns the URL where the user should be redirected after restoring.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('canonical')) {
      // Otherwise fall back to the default link template.
      return $entity->toUrl();
    }
    else {
      return Url::fromRoute('trash.admin_content_trash_entity_type', [
        'entity_type_id' => $entity->getEntityTypeId(),
      ]);
    }
  }

}
