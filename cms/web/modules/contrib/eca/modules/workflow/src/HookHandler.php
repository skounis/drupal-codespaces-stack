<?php

namespace Drupal\eca_workflow;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\eca\EntityOriginalTrait;
use Drupal\eca\Event\BaseHookHandler;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * The handler for hook implementations within the eca_base.module file.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class HookHandler extends BaseHookHandler {

  use EntityOriginalTrait;

  /**
   * The content entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $contentEntityTypes;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected ModerationInformationInterface $moderationInformation;

  /**
   * Set the content entity types service.
   *
   * @param \Drupal\eca\Service\ContentEntityTypes $content_entity_types
   *   The content entity types service.
   */
  public function setContentEntityTypes(ContentEntityTypes $content_entity_types): void {
    $this->contentEntityTypes = $content_entity_types;
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
   * Triggers moderation state transition when the entity is a moderated one.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   */
  public function transition(ContentEntityInterface $entity): void {
    if ($this->moderationInformation->isModeratedEntity($entity) && $entity->hasField('moderation_state')) {
      $original = $this->getOriginal($entity);
      $from_state = $original instanceof ContentEntityInterface ? $original->get('moderation_state')->value : NULL;
      $to_state = $entity->get('moderation_state')->value;
      if ($from_state !== $to_state) {
        $this->triggerEvent->dispatchFromPlugin('workflow:transition', $entity, $from_state, $to_state, $this->contentEntityTypes);
      }
    }
  }

}
