<?php

namespace Drupal\eca_content\Event;

/**
 * Content entity events.
 */
final class ContentEntityEvents {

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityBundleCreate event.
   *
   * @Event
   *
   * @var string
   */
  public const BUNDLECREATE = 'eca.content_entity.bundlecreate';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityBundleDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const BUNDLEDELETE = 'eca.content_entity.bundledelete';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityCreate event.
   *
   * @Event
   *
   * @var string
   */
  public const CREATE = 'eca.content_entity.create';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityRevisionCreate event.
   *
   * @Event
   *
   * @var string
   */
  public const REVISIONCREATE = 'eca.content_entity.revisioncreate';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityPreLoad event.
   *
   * @Event
   *
   * @var string
   */
  public const PRELOAD = 'eca.content_entity.preload';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityLoad event.
   *
   * @Event
   *
   * @var string
   */
  public const LOAD = 'eca.content_entity.load';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityStorageLoad event.
   *
   * @Event
   *
   * @var string
   */
  public const STORAGELOAD = 'eca.content_entity.storageload';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityPreSave event.
   *
   * @Event
   *
   * @var string
   */
  public const PRESAVE = 'eca.content_entity.presave';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityInsert event.
   *
   * @Event
   *
   * @var string
   */
  public const INSERT = 'eca.content_entity.insert';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityUpdate event.
   *
   * @Event
   *
   * @var string
   */
  public const UPDATE = 'eca.content_entity.update';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityTranslationCreate event.
   *
   * @Event
   *
   * @var string
   */
  public const TRANSLATIONCREATE = 'eca.content_entity.translationcreate';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityTranslationInsert event.
   *
   * @Event
   *
   * @var string
   */
  public const TRANSLATIONINSERT = 'eca.content_entity.translationinsert';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityTranslationDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const TRANSLATIONDELETE = 'eca.content_entity.translationdelete';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityPreDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const PREDELETE = 'eca.content_entity.predelete';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const DELETE = 'eca.content_entity.delete';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityRevisionDelete event.
   *
   * @Event
   *
   * @var string
   */
  public const REVISIONDELETE = 'eca.content_entity.revisiondelete';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityView event.
   *
   * @Event
   *
   * @var string
   */
  public const VIEW = 'eca.content_entity.view';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityView event.
   *
   * @Event
   *
   * @var string
   */
  public const VIEWMODEALTER = 'eca.content_entity.view_mode_alter';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityPrepareView event.
   *
   * @Event
   *
   * @var string
   */
  public const PREPAREVIEW = 'eca.content_entity.prepareview';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityPrepareForm event.
   *
   * @Event
   *
   * @var string
   */
  public const PREPAREFORM = 'eca.content_entity.prepareform';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityValidate event.
   *
   * @Event
   *
   * @var string
   */
  public const VALIDATE = 'eca.content_entity.validate';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityFieldValuesInit event.
   *
   * @Event
   *
   * @var string
   */
  public const FIELDVALUESINIT = 'eca.content_entity.fieldvaluesinit';

  /**
   * Identifies \Drupal\eca_content\Event\ContentEntityCustomEvent event.
   *
   * @Event
   *
   * @var string
   */
  public const CUSTOM = 'eca.content_entity.custom';

  /**
   * Identifies \Drupal\eca_content\Event\ReferenceSelection event.
   *
   * @Event
   *
   * @var string
   */
  public const REFERENCE_SELECTION = 'eca.content_entity.reference_selection';

  /**
   * Identifies \Drupal\eca_content\Event\OptionsSelection event.
   *
   * @Event
   *
   * @var string
   */
  public const OPTIONS_SELECTION = 'eca.content_entity.options_selection';

}
