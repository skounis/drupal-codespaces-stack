<?php

namespace Drupal\scheduler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\scheduler\Event\SchedulerEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a scheduler manager.
 */
class SchedulerManager {

  use StringTranslationTrait;

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Scheduler Logger service object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity Type Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Entity Field Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * Scheduler Plugin Manager service object.
   *
   * @var SchedulerPluginManager
   */
  private $pluginManager;

  /**
   * Constructs a SchedulerManager object.
   */
  public function __construct(
    DateFormatterInterface $dateFormatter,
    LoggerInterface $logger,
    ModuleHandlerInterface $moduleHandler,
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    EventDispatcherInterface $eventDispatcher,
    TimeInterface $time,
    EntityFieldManagerInterface $entityFieldManager,
    // Trailing comma is incompatible with PHPUnit 9.6.19 in Drupal 9.5 PHP 7.4.
    // phpcs:ignore Drupal.Functions.MultiLineFunctionDeclaration.MissingTrailingComma
    SchedulerPluginManager $pluginManager
  ) {
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
    $this->entityFieldManager = $entityFieldManager;
    $this->pluginManager = $pluginManager;
  }

  /**
   * Dispatch a Scheduler event.
   *
   * All Scheduler events should be dispatched through this common function.
   *
   * Drupal 8.8 and 8.9 use Symfony 3.4 and from Drupal 9.0 the Symfony version
   * is 4.4. Starting with Symfony 4.3 the signature of the event dispatcher
   * function has the parameters swapped round, the event object is first,
   * followed by the event name string. At 9.0 the existing signature has to be
   * used but from 9.1 the parameters must be switched.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event object.
   * @param string $event_name
   *   The text name for the event.
   *
   * @see https://www.drupal.org/project/scheduler/issues/3166688
   */
  public function dispatch(Event $event, string $event_name) {
    // \Symfony\Component\HttpKernel\Kernel::VERSION will give the symfony
    // version. However, testing this does not give the required outcome, we
    // need to test the Drupal core version.
    // @todo Remove the check when Core 9.1 is the lowest supported version.
    if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
      // The new way, with $event first.
      $this->eventDispatcher->dispatch($event, $event_name);
    }
    else {
      // Replicate the existing dispatch signature.
      $this->eventDispatcher->dispatch($event_name, $event);
    }
  }

  /**
   * Dispatches a Scheduler event for an entity.
   *
   * This function dispatches a Scheduler event, identified by $event_id, for
   * the entity type of the provided $entity. Each entity type has its own
   * events class Scheduler{EntityType}Events, for example SchedulerNodeEvents,
   * SchedulerMediaEvents, etc. This class contains constants (with names
   * matching the $event_id parameter) which uniquely define the final event
   * name string to be dispatched. The actual event object dispatched is always
   * of class SchedulerEvent.
   *
   * The $entity is passed by reference so that any changes made in the event
   * subscriber implementations are automatically stored and passed forward.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string $event_id
   *   The short text id the event, for example 'PUBLISH' or 'PRE_UNPUBLISH'.
   */
  public function dispatchSchedulerEvent(EntityInterface &$entity, string $event_id) {
    // Get the fully named-spaced event class name for the entity type, for use
    // in the constant() function.
    $event_class = $this->getPlugin($entity->getEntityTypeId())->schedulerEventClass();
    $event_name = constant("$event_class::$event_id");

    // Create the event object and dispatch the required event_name.
    $event = new SchedulerEvent($entity);
    $this->dispatch($event, $event_name);
    // Get the entity, as it may have been modified by an event subscriber.
    $entity = $event->getEntity();
  }

  /**
   * Handles throwing exceptions.
   *
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity causing the exception.
   * @param string $exception_name
   *   Which exception to throw.
   * @param string $process
   *   The process being performed (publish|unpublish).
   *
   * @throws \Drupal\scheduler\Exception\SchedulerEntityTypeNotEnabledException
   */
  private function throwSchedulerException(EntityInterface $entity, $exception_name, $process) {
    $plugin = $this->getPlugin($entity->getEntityTypeId());

    // Exception messages are developer-facing and do not need to be translated
    // from English. So it is acceptable to create words such as "{$process}ed"
    // and "{$process}ing".
    switch ($exception_name) {
      case 'SchedulerEntityTypeNotEnabledException':
        $message = "'%s' (id %d) was not %s because %s %s '%s' is not enabled for scheduled %s. One of the following hook functions added the id incorrectly: %s. Processing halted";
        $p1 = $entity->label();
        $p2 = $entity->id();
        $p3 = "{$process}ed";
        $p4 = $entity->getEntityTypeId();
        $p5 = $plugin->typeFieldName();
        $p6 = $entity->{$plugin->typeFieldName()}->entity->label();
        $p7 = "{$process}ing";
        // Get a list of the hook function implementations, as one of these will
        // have caused this exception.
        $hooks = array_merge(
          $this->getHookImplementations('list', $entity),
          $this->getHookImplementations('list_alter', $entity)
        );
        asort($hooks);
        $p8 = implode(', ', $hooks);
        break;
    }

    $class = "\\Drupal\\scheduler\\Exception\\$exception_name";
    throw new $class(sprintf($message, $p1, $p2, $p3, $p4, $p5, $p6, $p7, $p8));
  }

  /**
   * Publish scheduled entities.
   *
   * @return bool
   *   TRUE if any entity has been published, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerEntityTypeNotEnabledException
   */
  public function publish() {
    $result = FALSE;
    $process = 'publish';
    $plugins = $this->getPlugins();

    foreach ($plugins as $entityTypeId => $plugin) {
      // Select all entities of the types for this plugin that are enabled for
      // scheduled publishing and where publish_on is less than or equal to the
      // current time.
      $ids = [];
      $scheduler_enabled_types = $this->getEnabledTypes($entityTypeId, $process);

      if (!empty($scheduler_enabled_types)) {
        $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery()
          ->exists('publish_on')
          ->condition('publish_on', $this->time->getRequestTime(), '<=')
          ->condition($plugin->typeFieldName(), $scheduler_enabled_types, 'IN')
          ->sort('publish_on');
        // Disable access checks for this query.
        // @see https://www.drupal.org/node/2700209
        $query->accessCheck(FALSE);
        // If the entity type is revisionable then make sure we look for the
        // latest revision. This is important for moderated entities.
        if ($plugin->entityTypeObject()->isRevisionable()) {
          $query->latestRevision();
        }
        // Add tags to let other modules alter the query.
        $query->addTag('scheduler');
        $query->addTag('scheduler_publish');
        $query->addTag('scheduler_' . $entityTypeId . '_publish');
        $ids = $query->execute();
      }

      // Allow other modules to add to the list of entities to be published.
      $hook_implementations = $this->getHookImplementations('list', $entityTypeId);
      foreach ($hook_implementations as $function) {
        // Cast each hook result as array, to protect from bad implementations.
        $ids = array_merge($ids, (array) $function($process, $entityTypeId));
      }

      // Allow other modules to alter the list of entities to be published.
      $hook_implementations = $this->getHookImplementations('list_alter', $entityTypeId);
      foreach ($hook_implementations as $function) {
        $function($ids, $process, $entityTypeId);
      }

      // Finally ensure that there are no duplicates in the list of ids.
      $ids = array_unique($ids);

      // In 8.x the entity translations are all associated with one entity id
      // unlike 7.x where each translation was a separate id. This means that
      // the list of ids returned above may have some translations that need
      // processing now and others that do not.
      /** @var \Drupal\Core\Entity\EntityInterface[] $entities */
      $entities = $this->loadEntities($ids, $entityTypeId);
      foreach ($entities as $entity_multilingual) {

        // The API calls could return entities of types which are not enabled
        // for scheduled publishing, so do not process these. This check can be
        // done once as the setting will be the same for all translations.
        if (!$this->getThirdPartySetting($entity_multilingual, 'publish_enable', $this->setting('default_publish_enable'))) {
          $this->throwSchedulerException($entity_multilingual, 'SchedulerEntityTypeNotEnabledException', $process);
        }

        $languages = $entity_multilingual->getTranslationLanguages();
        foreach ($languages as $language) {
          // The object returned by getTranslation() is a normal $entity.
          $entity = $entity_multilingual->getTranslation($language->getId());

          // If the current translation does not have a publish on value, or it
          // is later than the date we are processing then move on to the next.
          $publish_on = $entity->publish_on->value;
          if (empty($publish_on) || $publish_on > $this->time->getRequestTime()) {
            continue;
          }

          // Check that other modules allow the process on this entity.
          if (!$this->isAllowed($entity, $process)) {
            continue;
          }

          // Trigger the PRE_PUBLISH Scheduler event so that modules can react
          // before the entity is published.
          $this->dispatchSchedulerEvent($entity, 'PRE_PUBLISH');

          // Update 'changed' timestamp.
          if ($entity instanceof EntityChangedInterface) {
            $entity->setChangedTime($publish_on);
          }

          $msg_extra = '';

          // If required, set the created date to match published date.
          if ($this->getThirdPartySetting($entity, 'publish_touch', $this->setting('default_publish_touch')) ||
            ($this->getThirdPartySetting($entity, 'publish_past_date_created', $this->setting('default_publish_past_date_created')) && $entity->getCreatedTime() > $publish_on)
          ) {
            $old_creation_date = $entity->getCreatedTime();
            $entity->setCreatedTime($publish_on);
            $msg_extra = $this->t('The previous creation date was @old_creation_date, now updated to match the publishing date.', [
              '@old_creation_date' => $this->dateFormatter->format($old_creation_date, 'short'),
            ]);
          }

          $create_publishing_revision = $this->getThirdPartySetting($entity, 'publish_revision', $this->setting('default_publish_revision'));
          if ($create_publishing_revision && $entity->getEntityType()->isRevisionable()) {
            $entity->setNewRevision();
            // Use a core date format to guarantee a time is included.
            $revision_log_message = rtrim($this->t('Published by Scheduler. The scheduled publishing date was @publish_on.', [
              '@publish_on' => $this->dateFormatter->format($publish_on, 'short'),
            ]) . ' ' . $msg_extra);
            $entity->setRevisionLogMessage($revision_log_message)
              ->setRevisionCreationTime($this->time->getRequestTime());
          }
          // Unset publish_on so the entity will not get rescheduled by any
          // interim calls to $entity->save().
          $entity->publish_on->value = NULL;

          // Invoke all implementations of hook_scheduler_publish_process() and
          // hook_scheduler_{type}_publish_process() to allow other modules to
          // do the "publishing" process instead of Scheduler.
          $hook_implementations = $this->getHookImplementations('publish_process', $entity);
          $successful_hooks = [];
          $failed_hooks = [];
          foreach ($hook_implementations as $function) {
            $return = $function($entity);
            if ($return === 1) {
              $successful_hooks[] = $function;
              if (stristr($function, '_action')) {
                // If this is a legacy action hook, for safety call ->save() as
                // this used to be done here in Scheduler 8.x-1.x.
                $entity->save();
              }
            }
            $return === -1 ? $failed_hooks[] = $function : NULL;
          }
          $processed = count($successful_hooks) > 0;
          $failed = count($failed_hooks) > 0;

          // Create a set of variables for use in the log message.
          $bundle_type = $entity->getEntityType()->getBundleEntityType();
          $entity_type = $this->entityTypeManager->getStorage($bundle_type)->load($entity->bundle());
          $links = [];
          if ($entity->hasLinkTemplate('canonical')) {
            $links[] = $entity->toLink($this->t('View @type', [
              '@type' => strtolower($entity_type->label()),
            ]))->toString();
          }
          if ($entity_type->hasLinkTemplate('edit-form')) {
            $links[] = $entity_type->toLink($this->t('@label settings', [
              '@label' => $entity_type->label(),
            ]), 'edit-form')->toString();
          }
          $logger_variables = [
            '@type' => $entity_type->label(),
            '%title' => $entity->label(),
            '@successful_hooks' => implode(', ', $successful_hooks),
            '@failed_hooks' => implode(', ', $failed_hooks),
            'link' => implode(' ', $links),
          ];

          if ($failed) {
            // At least one hook function returned a failure or exception, so
            // stop processing this entity and move on to the next one.
            $this->logger->warning('Publishing failed for %title. @failed_hooks returned a failure code.', $logger_variables);
            // Restore the publish_on date to allow another attempt next time.
            $entity->publish_on->value = $publish_on;
            $entity->save();
            continue;
          }
          elseif ($processed) {
            // The entity was 'published' by a module implementing the hook, so
            // we only need to log this result.
            $this->logger->notice('@type: scheduled "publish" processing of %title completed by @successful_hooks.', $logger_variables);
          }
          else {
            // None of the above hook calls processed the entity and there were
            // no errors detected so set the entity to published.
            $this->logger->notice('@type: scheduled publishing of %title.', $logger_variables);

            // Use the actions system to publish and save the entity.
            $action_id = $plugin->publishAction();
            if ($this->moduleHandler->moduleExists('workbench_moderation_actions')) {
              // workbench_moderation_actions module replaces the standard
              // action with a custom one which should be used only when the
              // entity type is part of a moderation workflow.
              /** @var \Drupal\workbench_moderation\ModerationInformationInterface $moderation_info */
              $moderation_info = \Drupal::service('workbench_moderation.moderation_information');
              if ($moderation_info->isModeratableEntity($entity)) {
                $action_id = 'state_change__' . $entityTypeId . '__published';
              }
            }
            if ($loaded_action = $this->entityTypeManager->getStorage('action')->load($action_id)) {
              $loaded_action->getPlugin()->execute($entity);
            }
            else {
              // Fallback to the direct method if the action does not exist.
              $entity->setPublished()->save();
            }
          }

          // Invoke event to tell Rules that Scheduler has published the entity.
          if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
            _scheduler_rules_integration_dispatch_cron_event($entity, $process);
          }

          // Trigger the PUBLISH Scheduler event so that modules can react after
          // the entity is published.
          $this->dispatchSchedulerEvent($entity, 'PUBLISH');

          $result = TRUE;
        }
      }
    }

    return $result;
  }

  /**
   * Unpublish scheduled entities.
   *
   * @return bool
   *   TRUE if any entity has been unpublished, FALSE otherwise.
   *
   * @throws \Drupal\scheduler\Exception\SchedulerEntityTypeNotEnabledException
   */
  public function unpublish() {
    $result = FALSE;
    $process = 'unpublish';
    $plugins = $this->getPlugins();

    foreach ($plugins as $entityTypeId => $plugin) {
      // Select all entities of the types for this plugin that are enabled for
      // scheduled unpublishing and where unpublish_on is less than or equal to
      // the current time.
      $ids = [];
      $scheduler_enabled_types = $this->getEnabledTypes($entityTypeId, $process);

      if (!empty($scheduler_enabled_types)) {
        $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery()
          ->exists('unpublish_on')
          ->condition('unpublish_on', $this->time->getRequestTime(), '<=')
          ->condition($plugin->typeFieldName(), $scheduler_enabled_types, 'IN')
          ->sort('unpublish_on');
        // Disable access checks for this query.
        // @see https://www.drupal.org/node/2700209
        $query->accessCheck(FALSE);
        // If the entity type is revisionable then make sure we look for the
        // latest revision. This is important for moderated entities.
        if ($plugin->entityTypeObject()->isRevisionable()) {
          $query->latestRevision();
        }
        // Add tags to let other modules alter the query.
        $query->addTag('scheduler');
        $query->addTag('scheduler_unpublish');
        $query->addTag('scheduler_' . $entityTypeId . '_unpublish');
        $ids = $query->execute();
      }

      // Allow other modules to add to the list of entities to be unpublished.
      $hook_implementations = $this->getHookImplementations('list', $entityTypeId);
      foreach ($hook_implementations as $function) {
        // Cast each hook result as array, to protect from bad implementations.
        $ids = array_merge($ids, (array) $function($process, $entityTypeId));
      }

      // Allow other modules to alter the list of entities to be unpublished.
      $hook_implementations = $this->getHookImplementations('list_alter', $entityTypeId);
      foreach ($hook_implementations as $function) {
        $function($ids, $process, $entityTypeId);
      }

      // Finally ensure that there are no duplicates in the list of ids.
      $ids = array_unique($ids);

      /** @var \Drupal\Core\Entity\EntityInterface[] $entities */
      $entities = $this->loadEntities($ids, $entityTypeId);
      foreach ($entities as $entity_multilingual) {

        // The API calls could return entities of types which are not enabled
        // for scheduled unpublishing, so do not process these. This check can
        // be done once as the setting will be the same for all translations.
        if (!$this->getThirdPartySetting($entity_multilingual, 'unpublish_enable', $this->setting('default_unpublish_enable'))) {
          $this->throwSchedulerException($entity_multilingual, 'SchedulerEntityTypeNotEnabledException', $process);
        }

        $languages = $entity_multilingual->getTranslationLanguages();
        foreach ($languages as $language) {
          // The object returned by getTranslation() is a normal $entity.
          $entity = $entity_multilingual->getTranslation($language->getId());

          // If the current translation does not have an unpublish-on value, or
          // it is later than the date we are processing then move to the next.
          $unpublish_on = $entity->unpublish_on->value;
          if (empty($unpublish_on) || $unpublish_on > $this->time->getRequestTime()) {
            continue;
          }

          // Do not process the entity if it still has a publish_on time which
          // is in the past, as this implies that scheduled publishing has been
          // blocked by one of the hook functions we provide, and is still being
          // blocked now that the unpublishing time has been reached.
          $publish_on = $entity->publish_on->value;
          if (!empty($publish_on) && $publish_on <= $this->time->getRequestTime()) {
            continue;
          }

          // Check that other modules allow the process on this entity.
          if (!$this->isAllowed($entity, $process)) {
            continue;
          }

          // Trigger the PRE_UNPUBLISH Scheduler event so that modules can react
          // before the entity is unpublished.
          $this->dispatchSchedulerEvent($entity, 'PRE_UNPUBLISH');

          // Update 'changed' timestamp.
          if ($entity instanceof EntityChangedInterface) {
            $entity->setChangedTime($unpublish_on);
          }

          $create_unpublishing_revision = $this->getThirdPartySetting($entity, 'unpublish_revision', $this->setting('default_unpublish_revision'));
          if ($create_unpublishing_revision && $entity->getEntityType()->isRevisionable()) {
            $entity->setNewRevision();
            // Use a core date format to guarantee a time is included.
            $revision_log_message = $this->t('Unpublished by Scheduler. The scheduled unpublishing date was @unpublish_on.', [
              '@unpublish_on' => $this->dateFormatter->format($unpublish_on, 'short'),
            ]);
            // Create the new revision, setting message and revision timestamp.
            $entity->setRevisionLogMessage($revision_log_message)
              ->setRevisionCreationTime($this->time->getRequestTime());
          }
          // Unset publish_on so the entity will not get rescheduled by any
          // interim calls to $entity->save().
          $entity->unpublish_on->value = NULL;

          // Invoke all implementations of hook_scheduler_unpublish_process()
          // and hook_scheduler_{type}_unpublish_process() to allow other
          // modules to do the "unpublishing" process instead of Scheduler.
          $hook_implementations = $this->getHookImplementations('unpublish_process', $entity);
          $successful_hooks = [];
          $failed_hooks = [];
          foreach ($hook_implementations as $function) {
            $return = $function($entity);
            if ($return === 1) {
              $successful_hooks[] = $function;
              if (stristr($function, '_action')) {
                // If this is a legacy action hook, for safety call ->save() as
                // this used to be done here in Scheduler 8.x-1.x.
                $entity->save();
              }
            }
            $return === -1 ? $failed_hooks[] = $function : NULL;
          }
          $processed = count($successful_hooks) > 0;
          $failed = count($failed_hooks) > 0;

          // Create a set of variables for use in the log message.
          $bundle_type = $entity->getEntityType()->getBundleEntityType();
          $entity_type = $this->entityTypeManager->getStorage($bundle_type)->load($entity->bundle());
          $links = [];
          if ($entity->hasLinkTemplate('canonical')) {
            $links[] = $entity->toLink($this->t('View @type', [
              '@type' => strtolower($entity_type->label()),
            ]))->toString();
          }
          if ($entity_type->hasLinkTemplate('edit-form')) {
            $links[] = $entity_type->toLink($this->t('@label settings', [
              '@label' => $entity_type->label(),
            ]), 'edit-form')->toString();
          }
          $logger_variables = [
            '@type' => $entity_type->label(),
            '%title' => $entity->label(),
            '@successful_hooks' => implode(', ', $successful_hooks),
            '@failed_hooks' => implode(', ', $failed_hooks),
            'link' => implode(' ', $links),
          ];

          if ($failed) {
            // At least one hook function returned a failure or exception, so
            // stop processing this entity and move on to the next one.
            $this->logger->warning('Unpublishing failed for %title. @failed_hooks returned a failure code.', $logger_variables);
            // Restore the unpublish_on date to allow another attempt next time.
            $entity->unpublish_on->value = $unpublish_on;
            $entity->save();
            continue;
          }
          elseif ($processed) {
            // The entity was 'unpublished' by a module implementing the hook,
            // so we only need to log this result.
            $this->logger->notice('@type: scheduled "unpublish" processing of %title completed by @successful_hooks.', $logger_variables);
          }
          else {
            // None of the above hook calls processed the entity and there were
            // no errors detected so set the entity to unpublished.
            $this->logger->notice('@type: scheduled unpublishing of %title.', $logger_variables);

            // Use the actions system to unpublish and save the entity.
            $action_id = $plugin->unpublishAction();
            if ($this->moduleHandler->moduleExists('workbench_moderation_actions')) {
              // workbench_moderation_actions module replaces the standard
              // action with a custom one which should be used only when the
              // entity type is part of a moderation workflow.
              /** @var \Drupal\workbench_moderation\ModerationInformationInterface $moderation_info */
              $moderation_info = \Drupal::service('workbench_moderation.moderation_information');
              if ($moderation_info->isModeratableEntity($entity)) {
                $action_id = 'state_change__' . $entityTypeId . '__archived';
              }
            }
            if ($loaded_action = $this->entityTypeManager->getStorage('action')->load($action_id)) {
              $loaded_action->getPlugin()->execute($entity);
            }
            else {
              // Fallback to the direct method if the action does not exist.
              $entity->setUnpublished()->save();
            }
          }

          // Invoke event to tell Rules that Scheduler has unpublished the
          // entity.
          if ($this->moduleHandler->moduleExists('scheduler_rules_integration')) {
            _scheduler_rules_integration_dispatch_cron_event($entity, $process);
          }

          // Trigger the UNPUBLISH Scheduler event so that modules can react
          // after the entity is unpublished.
          $this->dispatchSchedulerEvent($entity, 'UNPUBLISH');

          $result = TRUE;
        }
      }
    }

    return $result;
  }

  /**
   * Checks whether a scheduled process on an entity is allowed.
   *
   * Other modules can prevent scheduled publishing or unpublishing by
   * implementing any or all of the following:
   *   hook_scheduler_publishing_allowed()
   *   hook_scheduler_unpublishing_allowed()
   *   hook_scheduler_{type}_publishing_allowed()
   *   hook_scheduler_{type}_unpublishing_allowed()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which the process is to be performed.
   * @param string $process
   *   The process to be checked. Values are 'publish' or 'unpublish'.
   *
   * @return bool
   *   TRUE if the process is allowed, FALSE if not.
   */
  public function isAllowed(EntityInterface $entity, $process) {
    // Default to TRUE.
    $result = TRUE;

    // Get all implementations of the required hook function.
    $hook_implementations = $this->getHookImplementations($process . 'ing_allowed', $entity);

    // Call the hook functions. If any specifically return FALSE the overall
    // result is FALSE. If a hook returns nothing it will not affect the result.
    foreach ($hook_implementations as $function) {
      $returned = $function($entity);
      $result &= !(isset($returned) && $returned == FALSE);
    }
    return $result;
  }

  /**
   * Returns an array of hook function names implemented for a hook type.
   *
   * The return array will include all implementations of the general hook
   * function called for all entity types, plus all implemented hooks for the
   * specific type of entity being processed. In addition, for node entities,
   * the original hook functions (prior to entity plugins) are added to maintain
   * backwards-compatibility.
   *
   * @param string $hookType
   *   The identifier of the hook function, for example 'publish_process' or
   *   'unpublishing_allowed' or 'hide_publish_date'.
   * @param \Drupal\Core\Entity\EntityInterface|string $entity
   *   The entity object which is being processed, or a string containing the
   *   entity type id (for example 'node' or 'media').
   *
   * @return array
   *   An array of callable function names for the implementations of this hook
   *   function for the type of entity being processed.
   */
  public function getHookImplementations(string $hookType, $entity) {
    $entityTypeId = (is_object($entity)) ? $entity->getEntityTypeid() : $entity;
    $hooks = [$hookType, "{$entityTypeId}_{$hookType}"];

    // For backwards compatibility the original node hook is also added.
    if ($entityTypeId == 'node') {
      $legacy_node_hooks = [
        'hide_publish_date' => 'hide_publish_on_field',
        'hide_unpublish_date' => 'hide_unpublish_on_field',
        'list' => 'nid_list',
        'list_alter' => 'nid_list_alter',
        'publish_process' => 'publish_action',
        'unpublish_process' => 'unpublish_action',
        'publishing_allowed' => 'allow_publishing',
        'unpublishing_allowed' => 'allow_unpublishing',
      ];
      $hooks[] = $legacy_node_hooks[$hookType];
    }

    // Find all modules that implement these hooks, then append the $hookName to
    // the end of the module, thus giving the full function name.
    $all_hook_implementations = [];
    foreach ($hooks as $hook) {
      $hookName = "scheduler_$hook";
      if (version_compare(\Drupal::VERSION, '9.4', '>=')) {
        // getImplementations() is deprecated in D9.4, use invokeAllWith().
        $this->moduleHandler->invokeAllWith($hookName, function (callable $hook, string $module) use ($hookName, &$all_hook_implementations) {
          $all_hook_implementations[] = $module . "_" . $hookName;
        });
      }
      else {
        // Use getImplementations() to maintain compatibility with Drupal 8.9.
        $implementations = $this->moduleHandler->getImplementations($hookName);
        array_walk($implementations, function (&$module) use ($hookName, &$all_hook_implementations) {
          $all_hook_implementations[] = $module . "_" . $hookName;
        });
      }
    }
    return $all_hook_implementations;
  }

  /**
   * Gives details and throws exception when a required action is missing.
   *
   * This displays a screen error message which is useful if the cron run was
   * initiated via the site UI. This will also be shown on the terminal if cron
   * was run via drush. If the Config Update module is installed then a link is
   * given to the actions report in Config UI, which lists the missing items and
   * provides a button to import from source. If Config Update is not installed
   * then a link is provided to its Drupal project page.
   *
   * @param string $action_id
   *   The id of the missing action.
   * @param string $process
   *   The Scheduler process being run, 'publish' or 'unpublish'.
   */
  protected function missingAction(string $action_id, string $process) {
    $logger_variables = ['%action_id' => $action_id];
    // If the Config Update module is available then link to the UI report. If
    // not then link to the project page on drupal.org.
    if (\Drupal::moduleHandler()->moduleExists('config_update')) {
      // If the report UI sub-module is enabled then link directly to the
      // actions report. Otherwise link to 'Extend' so it can be enabled.
      if (\Drupal::moduleHandler()->moduleExists('config_update_ui')) {
        $link = Link::fromTextAndUrl($this->t('Config Update for actions'), Url::fromRoute('config_update_ui.report', [
          'report_type' => 'type',
          'name' => 'action',
        ]));
      }
      else {
        $link = Link::fromTextAndUrl($this->t('Enable Config Update Reports'), Url::fromRoute('system.modules_list', ['filter' => 'config_update']));
      }
      $logger_variables['link'] = $link->toString();
      $logger_variables[':url'] = $link->getUrl()->toString();
    }
    else {
      $project_page = 'https://www.drupal.org/project/config_update';
      $logger_variables[':url'] = $project_page;
      $logger_variables['link'] = Link::fromTextAndUrl('Config Update project page', Url::fromUri($project_page))->toString();
    }

    \Drupal::messenger()->addError($this->t("Action '%action_id' is missing. Use <a href=':url'>Config Update</a> to import the missing action.", $logger_variables));
    $this->logger->warning("Action '%action_id' is missing. Use Config Update to import the missing action.", $logger_variables);
    throw new \Exception("Action '{$action_id}' is missing. Scheduled $process halted.");
  }

  /**
   * Run the lightweight cron.
   *
   * The Scheduler part of the processing performed here is the same as in the
   * normal Drupal cron run. The difference is that only scheduler_cron() is
   * executed, no other modules hook_cron() functions are called.
   *
   * This function is called from the external crontab job via url
   * /scheduler/cron/{access key} or it can be run interactively from the
   * Scheduler configuration page at /admin/config/content/scheduler/cron.
   * It is also executed when running Scheduler Cron via drush.
   *
   * @param array $options
   *   Options passed from drush command or admin form.
   */
  public function runLightweightCron(array $options = []) {
    // When calling via drush the log messages can be avoided by using --nolog.
    $log = $this->setting('log') && empty($options['nolog']);
    if ($log) {
      if (array_key_exists('nolog', $options)) {
        $trigger = 'drush command';
      }
      elseif (array_key_exists('admin_form', $options)) {
        $trigger = 'admin user form';
      }
      else {
        $trigger = 'url';
      }
      // This has to be 'notice' not 'info' so that drush can show the message.
      $this->logger->notice('Lightweight cron run activated by @trigger.', ['@trigger' => $trigger]);
    }
    scheduler_cron();
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      if (isset($handlers[0]) && $handlers[0] == 'default output handler') {
        ob_clean();
      }
    }
    if ($log) {
      $link = Link::fromTextAndUrl($this->t('settings'), Url::fromRoute('scheduler.cron_form'));
      $this->logger->notice('Lightweight cron run completed.', ['link' => $link->toString()]);
    }
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The value of the configuration item requested.
   */
  protected function setting($key) {
    return $this->configFactory->get('scheduler.settings')->get($key);
  }

  /**
   * Get third-party setting for an entity type, via the entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $setting
   *   The setting to retrieve.
   * @param mixed $default
   *   The default value for setting if none is found.
   *
   * @return mixed
   *   The value of the setting.
   */
  public function getThirdPartySetting(EntityInterface $entity, $setting, $default) {
    $typeFieldName = $this->getPlugin($entity->getEntityTypeId())->typeFieldName();
    if (empty($entity->$typeFieldName)) {
      // Avoid exception and give details if the typeFieldName does not exist.
      $params = [
        '%field' => $typeFieldName,
        '%id' => $this->getPlugin($entity->getEntityTypeId())->getPluginId(),
        '%entity' => $entity->getEntityTypeId(),
      ];
      \Drupal::messenger()->addError($this->t("Field '%field' specified by typeFieldName in the Scheduler plugin %id is not found in entity type %entity", $params));
      $this->logger->error("Field '%field' specified by typeFieldName in the Scheduler plugin %id is not found in entity type %entity", $params);
      return $default;
    }
    else {
      return $entity->$typeFieldName->entity->getThirdPartySetting('scheduler', $setting, $default);
    }
  }

  /**
   * Helper method to load latest revision of each entity.
   *
   * @param array $ids
   *   Array of entity ids.
   * @param string $type
   *   The type of entity.
   *
   * @return array
   *   Array of loaded entity objects, keyed by id.
   */
  protected function loadEntities(array $ids, string $type) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($type);
    $entities = [];
    foreach ($ids as $id) {
      // Avoid errors when an implementation of hook_scheduler_{type}_list has
      // added an id of the wrong type.
      if (!$entity = $storage->load($id)) {
        $this->logger->warning('Entity id @id is not a @type entity. Processing skipped.', [
          '@id' => $id,
          '@type' => $type,
        ]);
        continue;
      }
      // If the entity type is revisionable then load the latest revision. For
      // moderated entities this may be an unpublished draft update of a
      // currently published entity.
      if ($entity->getEntityType()->isRevisionable()) {
        $vid = $storage->getLatestRevisionId($id);
        $entities[$id] = $storage->loadRevision($vid);
      }
      else {
        $entities[$id] = $entity;
      }
    }
    return $entities;
  }

  /**
   * Get a list of all scheduler plugin definitions.
   *
   * @return array|mixed[]|null
   *   A list of definitions for the registered scheduler plugins.
   */
  public function getPluginDefinitions() {
    $plugin_definitions = $this->pluginManager->getDefinitions();
    // Sort in reverse order so that we have 'node_scheduler' followed by
    // 'media_scheduler'. When a third entity type plugin gets implemented it
    // would be possible to add a 'weight' property and sort by that.
    arsort($plugin_definitions);
    return $plugin_definitions;
  }

  /**
   * Gets instances of applicable Scheduler plugins for the enabled modules.
   *
   * @param string $provider
   *   Optional. Filter the plugins to return only those that are provided by
   *   the named $provider module.
   *
   * @return array
   *   Array of plugin objects, keyed by the entity type the plugin supports.
   */
  public function getPlugins(?string $provider = NULL) {
    $cache = \Drupal::cache()->get('scheduler.plugins');
    if (!empty($cache) && !empty($cache->data) && empty($provider)) {
      return $cache->data;
    }

    $definitions = $this->getPluginDefinitions();
    $plugins = [];
    foreach ($definitions as $definition) {
      $plugin = $this->pluginManager->createInstance($definition['id']);
      $dependency = $plugin->dependency();
      // Ignore plugins if there is a dependency module and it is not enabled.
      if ($dependency && !\Drupal::moduleHandler()->moduleExists($dependency)) {
        continue;
      }
      // Ignore plugins that do not match the specified provider module name.
      if ($provider && $definition['provider'] != $provider) {
        continue;
      }
      $plugins[$plugin->entityType()] = $plugin;
    }

    // Save to the cache only when not filtered for a particular a provider.
    if (empty($provider)) {
      \Drupal::cache()->set('scheduler.plugins', $plugins);
    }
    return $plugins;
  }

  /**
   * Reset the scheduler plugins cache.
   */
  public function invalidatePluginCache() {
    \Drupal::cache()->invalidate('scheduler.plugins');
  }

  /**
   * Get the supported entity types applicable to the currently enabled modules.
   *
   * @param string $provider
   *   Optional. Filter the returned entity types for only those from the
   *   plugins that are provided by the named $provider module.
   *
   * @return array
   *   A list of the entity type ids.
   */
  public function getPluginEntityTypes(?string $provider = NULL) {
    return array_keys($this->getPlugins($provider));
  }

  /**
   * Get a plugin for a specific entity type.
   *
   * @param string $entityTypeId
   *   The entity type id, for example 'node' or 'media'.
   *
   * @return mixed
   *   The plugin object associated with a specific entity, or NULL if none.
   */
  public function getPlugin($entityTypeId) {
    $plugins = $this->getPlugins();
    return $plugins[$entityTypeId] ?? NULL;
  }

  /**
   * Gets the names of the types/bundles enabled for a specific process.
   *
   * If the entity type is not supported by Scheduler, or there are no enabled
   * bundles for this process within the entity type, then an empty array is
   * returned.
   *
   * @param string $entityTypeId
   *   The entity type id, for example 'node' or 'media'.
   * @param string $process
   *   The process to check - 'publish' or 'unpublish'.
   *
   * @return array
   *   The entity's type/bundle names that are enabled for the required process.
   */
  public function getEnabledTypes($entityTypeId, $process) {
    if (!$plugin = $this->getPlugin($entityTypeId)) {
      return [];
    }
    $types = $plugin->getTypes();
    $types = array_filter($types, function ($bundle) use ($process) {
      return $bundle->getThirdPartySetting('scheduler', $process . '_enable', $this->setting('default_' . $process . '_enable'));
    });
    return array_keys($types);
  }

  /**
   * Gets list of entity add/edit form IDs.
   *
   * @return array
   *   List of entity add/edit form IDs for all registered scheduler plugins.
   */
  public function getEntityFormIds() {
    $plugins = $this->getPlugins();
    $form_ids = [];
    foreach ($plugins as $plugin) {
      $form_ids = array_merge($form_ids, $plugin->entityFormIDs());
    }
    return $form_ids;
  }

  /**
   * Gets list of entity type add/edit form IDs.
   *
   * @return array
   *   List of entity type add/edit form IDs for registered scheduler plugins.
   */
  public function getEntityTypeFormIds() {
    $plugins = $this->getPlugins();
    $form_ids = [];
    foreach ($plugins as $plugin) {
      $form_ids = array_merge($form_ids, $plugin->entityTypeFormIDs());
    }
    return $form_ids;
  }

  /**
   * Gets the supported Devel Generate form IDs.
   *
   * @return array
   *   List of form IDs used by Devel Generate, keyed by entity type.
   */
  public function getDevelGenerateFormIds() {
    $plugins = $this->getPlugins();
    $form_ids = [];
    foreach ($plugins as $entityTypeId => $plugin) {
      // The devel_generate form id is optional so only save if a value exists.
      // Use entity type as key so we can get back from form_id to entity.
      if ($form_id = $plugin->develGenerateForm()) {
        $form_ids[$entityTypeId] = $form_id;
      }
    }
    return $form_ids;
  }

  /**
   * Gets the routes for the entity collection pages.
   *
   * @return array
   *   List of routes for collection pages, keyed by entity type.
   */
  public function getCollectionRoutes() {
    $plugins = $this->getPlugins();
    $routes = [];
    foreach ($plugins as $entityTypeId => $plugin) {
      $routes[$entityTypeId] = $plugin->collectionRoute();
    }
    return $routes;
  }

  /**
   * Gets the routes for user profile page scheduled views.
   *
   * @return array
   *   List of routes for the user page views, keyed by entity type.
   */
  public function getUserPageViewRoutes() {
    $plugins = $this->getPlugins();
    $routes = [];
    foreach ($plugins as $entityTypeId => $plugin) {
      // The user view is optional so only save if there is a value.
      if ($route = $plugin->userViewRoute()) {
        $routes[$entityTypeId] = $route;
      }
    }
    return $routes;
  }

  /**
   * Derives the permission name for an entity type and permission type.
   *
   * This function is added because for backwards-compatibility the node
   * permission names have to end with 'nodes' and 'content'. For all other
   * newly-supported entity types it is $entityTypeId.
   *
   * @param string $entityTypeId
   *   The entity type id, for example 'node', 'media' etc.
   * @param string $permissionType
   *   The type of permission - 'schedule' or 'view'.
   *
   * @return string
   *   The internal name of the scheduler permission.
   */
  public function permissionName($entityTypeId, $permissionType) {
    switch ($permissionType) {
      case 'schedule':
        return 'schedule publishing of ' . ($entityTypeId == 'node' ? 'nodes' : $entityTypeId);

      case 'view':
        return 'view scheduled ' . ($entityTypeId == 'node' ? 'content' : $entityTypeId);

      default:
        return '* should never get this *';
    }
  }

  /**
   * Updates db tables for entities that should have the Scheduler fields.
   *
   * This is called from scheduler_modules_installed and scheduler_update_8201.
   * It can also be called manually via drush command scheduler-entity-update.
   *
   * @return array
   *   Labels of the entity types updated.
   */
  public function entityUpdate() {
    $entityUpdateManager = \Drupal::entityDefinitionUpdateManager();
    $updated = [];
    $list = $entityUpdateManager->getChangeList();
    foreach ($list as $entity_type_id => $definitions) {
      if (($definitions['field_storage_definitions']['publish_on'] ?? 0) || ($definitions['field_storage_definitions']['unpublish_on'] ?? 0)) {
        $entity_type = $entityUpdateManager->getEntityType($entity_type_id);
        $fields = scheduler_entity_base_field_info($entity_type);
        foreach ($fields as $field_name => $field_definition) {
          $entityUpdateManager->installFieldStorageDefinition($field_name, $entity_type_id, $entity_type_id, $field_definition);
        }
        $this->logger->notice('%entity entity type updated with %publish_on and %unpublish_on fields.', [
          '%entity' => $entity_type->getLabel(),
          '%publish_on' => $fields['publish_on']->getLabel(),
          '%unpublish_on' => $fields['unpublish_on']->getLabel(),
        ]);
        $updated[] = (string) $entity_type->getLabel();
      }
    }
    return $updated;
  }

  /**
   * Refreshes scheduler views from source.
   *
   * If the view exists in the site's active storage it will be updated from the
   * source yml file. If the view is now required but does not exist in active
   * storage it will be loaded.
   *
   * Called from scheduler_modules_installed() and scheduler_update_8202().
   *
   * @param array $only_these_types
   *   List of entity types to restrict the update of views to these types only.
   *   Optional. If none then revert/load all applicable scheduler views.
   *
   * @return array
   *   Labels of the views that were updated.
   */
  public function viewsUpdate(array $only_these_types = []) {
    $updated = [];
    $definition = $this->entityTypeManager->getDefinition('view');
    $view_storage = $this->entityTypeManager->getStorage('view');
    // Get the supported entity type ids for enabled modules where the provider
    // is Scheduler. Third-party plugins do not need to be processed here.
    $entity_types = $this->getPluginEntityTypes('scheduler');
    if ($only_these_types) {
      $entity_types = array_intersect($entity_types, $only_these_types);
    }

    foreach ($entity_types as $entity_type) {
      $name = 'scheduler_scheduled_' . ($entity_type == 'node' ? 'content' : $entity_type);
      $full_name = $definition->getConfigPrefix() . '.' . $name;

      // Read the view definition from the .yml file. First try the /optional
      // folder, then the main /config folder.
      $optional_folder = \Drupal::service('extension.list.module')->getPath('scheduler') . '/config/optional';
      $source_storage = new FileStorage($optional_folder);
      if (!$source = $source_storage->read($full_name)) {
        $install_folder = \Drupal::service('extension.list.module')->getPath('scheduler') . '/config/install';
        $source_storage = new FileStorage($install_folder);
        if (!$source = $source_storage->read($full_name)) {
          $this->logger->notice('No source file for %full_name in either %install_folder or %optional_folder folders',
            ['%full_name' => $full_name, '%install_folder' => $install_folder, '%optional_folder' => $optional_folder]);
          continue;
        }
      }

      // Try to read the view definition from active config storage.
      /** @var \Drupal\Core\Config\StorageInterface $config_storage */
      $config_storage = \Drupal::service('config.storage');
      if ($config_storage->read($full_name)) {
        // The view does exist in active storage, so load it, then replace the
        // value with the source, but retain the _core and uuid values.
        $view = $view_storage->load($name);
        $core = $view->get('_core');
        $uuid = $view->get('uuid');
        $view = $view_storage->updateFromStorageRecord($view, $source);
        $view->set('_core', $core);
        $view->set('uuid', $uuid);
        $view->save();
        $this->logger->info('%view view updated.', ['%view' => $source['label']]);
      }
      else {
        // The view does not exist in active storage so import it from source.
        $view = $view_storage->createFromStorageRecord($source);
        $view->save();
        $this->logger->info('%view view loaded from source.', ['%view' => $source['label']]);
      }
      $updated[] = $source['label'];
    }
    // The views are loaded OK but the publish-on and unpublish-on views field
    // handlers are not found. Clearing the views data cache solves the problem.
    Cache::invalidateTags(['views_data']);
    return $updated;
  }

  /**
   * Reverts entity types that are no longer supported by Scheduler plugins.
   *
   * In normal situations this function is not required. However in the case
   * when a plugin (either provided by Scheduler or another modules) is removed
   * after being used, the db fields and third-party-settings remain and have to
   * be deleted. This function was added to clean up the Paragraphs entity type
   * but has been made generic for future use. It is called from a hook_update()
   * and can also be run via drush command scheduler:entity-revert.
   * See https://www.drupal.org/project/scheduler/issues/3259200
   *
   * @param array $only_these_types
   *   Optional list of entity type ids to restrict the updates. If none given
   *   then reverts all applicable entity types that have schema changes showing
   *   that the db fields need to be removed.
   *
   * @return array
   *   Messages about the entity types reverted.
   */
  public function entityRevert(array $only_these_types = []) {
    // Find all changed entity definitions.
    $entityUpdateManager = \Drupal::entityDefinitionUpdateManager();
    $changeList = $entityUpdateManager->getChangeList();

    $output = [];
    if ($only_these_types) {
      // First remove any non-existent entity types requested.
      $all_entity_types = array_keys($this->entityTypeManager->getDefinitions());
      if ($unknown = array_diff($only_these_types, $all_entity_types)) {
        $output['unknown'] = $this->t('Unknown entity types (@unknown)', ['@unknown' => implode(' ', $unknown)]);
      }
      $entity_type_ids = array_intersect($only_these_types, $all_entity_types);
    }
    else {
      // Nothing given. Get the list of changed entity types.
      $entity_type_ids = array_keys($changeList);
    }
    // Remove any requested entity types that do have enabled plugins, as these
    // must not be reverted.
    $supported_types = $this->getPluginEntityTypes();
    $entity_type_ids = array_diff($entity_type_ids, $supported_types);

    foreach ($entity_type_ids as $entity_type_id) {
      $entityType = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundleType = $entityType->getBundleEntityType();

      // Remove the Scheduler fields from the entity type if they are shown in
      // the changeList as 'deleted'.
      if (isset($changeList[$entity_type_id]['field_storage_definitions'])) {
        foreach (['publish_on', 'unpublish_on'] as $field_name) {
          $change = ($changeList[$entity_type_id]['field_storage_definitions'][$field_name] ?? NULL);
          // If the field is marked as deleted then remove it.
          if ($change == $entityUpdateManager::DEFINITION_DELETED && $field = $entityUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id)) {
            $entityUpdateManager->uninstallFieldStorageDefinition($field);
            $output["{$entity_type_id} fields"] = $this->t('Scheduler fields removed from @entityType', [
              '@entityType' => $entityType->getLabel(),
            ]);
            $this->logger->info('%field field removed from %entityType entity type', [
              '%field' => $field->getLabel(),
              '%entityType' => $entityType->getLabel(),
            ]);
          }
        }
      }

      // Skip entity types without bundle types. This should not be necessary,
      // but it is better to use defensive programming just in case.
      if (empty($bundleType)) {
        continue;
      }

      // Remove Scheduler third-party-settings from each bundle.
      foreach ($this->entityTypeManager->getStorage($bundleType)->loadMultiple() as $bundle) {
        // Remove each third_party_setting. The last one to be removed will also
        // cause the 'scheduler' top-level array to be deleted.
        $third_party_settings = $bundle->getThirdPartySettings('scheduler');
        if ($third_party_settings) {
          foreach (array_keys($third_party_settings) as $setting) {
            $bundle->unsetThirdPartySetting('scheduler', $setting)->save();
          }
          $this->logger->info('Scheduler settings removed from %entity %bundle', [
            '%entity' => $bundle->getEntityType()->getLabel(),
            '%bundle' => $bundle->label(),
          ]);
          $output["{$bundle->id()} settings"] = $this->t('Settings removed from @bundle', [
            '@bundle' => $bundle->label(),
          ]);
        }
      }
    }

    return $output;
  }

  /**
   * Reset the form display fields to match the Scheduler enabled settings.
   *
   * The Scheduler fields are disabled by default and only enabled in a form
   * display when that entity bundle is enabled for scheduled publishing or
   * unpublishing. See _scheduler_form_entity_type_submit() for details.
   *
   * This was a design change during the development of Scheduler 2.0 and any
   * site that had installed Scheduler prior to 2.0-rc8 will have all fields
   * enabled. Whilst this should not be a problem, it is preferable to update
   * the displays to match the scenario when the modules is freshly installed.
   * Hence this function was added and called from scheduler_update_8208().
   */
  public function resetFormDisplayFields() {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $fields_displayed = [];
    $fields_hidden = [];

    foreach ($this->getPlugins() as $entityTypeId => $plugin) {
      // Get all active display modes. getFormModes() returns the additional
      // modes then add the default.
      $all_display_modes = array_keys($display_repository->getFormModes($entityTypeId));
      $all_display_modes[] = $display_repository::DEFAULT_DISPLAY_MODE;

      $supported_display_modes = $plugin->entityFormDisplayModes();

      $bundles = $plugin->getTypes();
      foreach ($bundles as $bundle_id => $bundle) {
        foreach ($all_display_modes as $display_mode) {
          $form_display = $display_repository->getFormDisplay($entityTypeId, $bundle_id, $display_mode);
          // If the form display is new and not saved yet, do nothing with it.
          // @see https://www.drupal.org/project/scheduler/issues/3359790
          if ($form_display->isNew()) {
            continue;
          }

          foreach (['publish', 'unpublish'] as $value) {
            $field = $value . '_on';
            $setting = $value . '_enable';
            // If this bundle is not enabled for scheduled (un)publishing or the
            // form display mode is not supported then remove the field.
            if (!$bundle->getThirdPartySetting('scheduler', $setting, FALSE) || !in_array($display_mode, $supported_display_modes)) {
              $form_display->removeComponent($field)->save();
              if ($display_mode == $display_repository::DEFAULT_DISPLAY_MODE) {
                $fields_hidden[$field]["{$bundle->getEntityType()->getCollectionLabel()}"][] = $bundle->label();
              }
            }
            else {
              // Scheduling is enabled. Get the existing component to preserve
              // any changed settings, but if the type is empty or set the to
              // the core default 'datetime_timestamp' then change it to
              // Scheduler's 'datetime_timestamp_no_default'.
              $component = $form_display->getComponent($field);
              if (empty($component['type']) || $component['type'] == 'datetime_timestamp') {
                $component['type'] = 'datetime_timestamp_no_default';
              }
              $component['weight'] = ($field == 'publish_on' ? 52 : 54);
              // Make sure the field and the settings group are displayed.
              $form_display->setComponent('scheduler_settings', ['weight' => 50])
                ->setComponent($field, $component)->save();
              if ($display_mode == $display_repository::DEFAULT_DISPLAY_MODE) {
                $fields_displayed[$field]["{$bundle->getEntityType()->getCollectionLabel()}"][] = $bundle->label();
              }
            }
          }
          // If the display mode is not supported remove the group fieldset.
          if (!in_array($display_mode, $supported_display_modes)) {
            $form_display->removeComponent('scheduler_settings')->save();
          }
        }
      }
    }

    // It is not possible to determine whether a field on an enabled entity type
    // had been manually hidden before this update. It is a rare scenario but
    // inform the admin that there is potentially some manual work to do.
    $uri = 'https://www.drupal.org/project/scheduler/issues/3320341';
    $link = Link::fromTextAndUrl($this->t('Scheduler issue 3320341'), Url::fromUri($uri));
    \Drupal::messenger()->addMessage($this->t(
      'The Scheduler fields are now hidden by default and automatically changed to be displayed when an entity
      bundle is enabled for scheduling. If you have previously manually hidden scheduler fields for enabled
      entity types then these fields will now be displayed. You will need to manually hide them again or
      implement hook_scheduler_hide_publish_date() or hook_scheduler_TYPE_hide_publish_date() and the
      equivalent for unpublish_date. See @issue for details.',
      ['@issue' => $link->toString()]), MessengerInterface::TYPE_STATUS, FALSE);
    $this->logger->warning(
      'The Scheduler fields are now hidden by default and automatically changed to be displayed when an entity
      bundle is enabled for scheduling. If you have previously manually hidden scheduler fields for enabled
      entity types then these fields will now be displayed. You will need to manually hide them again or
      implement hook_scheduler_hide_publish_date() or hook_scheduler_TYPE_hide_publish_date() and the
      equivalent for unpublish_date. See @issue for details.',
      ['@issue' => $link->toString(), 'link' => $link->toString()]
    );

    // Helper function to format the list of fields on bundles.
    $formatOutputText = function ($fields) {
      return implode(', ', array_map(function ($name, $bundles) {
        return "$name (" . implode(',', $bundles) . ")";
      }, array_keys($fields), $fields));
    };

    $output = [];
    if (isset($fields_displayed['publish_on'])) {
      $output[] = $this->t('Publish On field displayed for: @list', [
        '@list' => $formatOutputText($fields_displayed['publish_on']),
      ]);
    }
    if (isset($fields_displayed['unpublish_on'])) {
      $output[] = $this->t('Unpublish On field displayed for: @list', [
        '@list' => $formatOutputText($fields_displayed['unpublish_on']),
      ]);
    }
    if (isset($fields_hidden['publish_on'])) {
      $output[] = $this->t('Publish On field hidden for: @list', [
        '@list' => $formatOutputText($fields_hidden['publish_on']),
      ]);
    }
    if (isset($fields_hidden['unpublish_on'])) {
      $output[] = $this->t('Unpublish On field hidden for: @list', [
        '@list' => $formatOutputText($fields_hidden['unpublish_on']),
      ]);
    }
    return $output;
  }

}
