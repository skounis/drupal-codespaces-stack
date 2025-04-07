<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\eca\Attributes\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AccountEventInterface;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Event\RenderEventInterface;

/**
 * General ECA event subscriber for EcaEvents::BEFORE_INITIAL_EXECUTION events.
 */
class EcaExecutionGeneralSubscriber extends EcaExecutionSubscriberBase {

  /**
   * Subscriber method before initial execution.
   *
   * @param \Drupal\eca\Event\BeforeInitialExecutionEvent $before_event
   *   The according event.
   */
  #[Token(
    name: 'account',
    description: 'The user account of the event.',
    classes: [AccountEventInterface::class],
  )]
  #[Token(
    name: 'entity',
    description: 'The entity of the event.',
    classes: [EntityEventInterface::class],
  )]
  #[Token(
    name: 'ENTITY_TYPE',
    description: 'The entity of the event under the name of its entity type.',
    classes: [EntityEventInterface::class],
  )]
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $before_event): void {
    $plugin = $before_event->getEcaEvent()->getPlugin();
    $this->tokenService->addTokenDataProvider($plugin);

    $event = $before_event->getEvent();
    if ($event instanceof AccountEventInterface) {
      try {
        if ($user = $this->entityTypeManager->getStorage('user')->load($event->getAccount()->id())) {
          $this->tokenService->addTokenData('account', $user);
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        // Deliberately ignored.
      }
    }
    if ($event instanceof EntityEventInterface) {
      $entity = $event->getEntity();
      $this->tokenService->addTokenData('entity', $entity);
      if ($token_type = $this->tokenService->getTokenTypeForEntityType($entity->getEntityTypeId())) {
        $this->tokenService->addTokenData($token_type, $entity);
      }
    }
    if ($event instanceof RenderEventInterface) {
      $render_array = &$event->getRenderArray();
      $metadata = BubbleableMetadata::createFromRenderArray($render_array);
      // Vary by path, query arguments and user account.
      $metadata->addCacheContexts([
        'url.path',
        'url.query_args',
        'user',
        'user.permissions',
      ]);
      // Invalidate when ECA config changes.
      $metadata->addCacheTags(['config:eca_list']);
      $metadata->applyTo($render_array);
    }
  }

  /**
   * Subscriber method after initial execution.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $after_event
   *   The according event.
   */
  public function onAfterInitialExecution(AfterInitialExecutionEvent $after_event): void {
    $plugin = $after_event->getEcaEvent()->getPlugin();
    $this->tokenService->removeTokenDataProvider($plugin);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = ['onBeforeInitialExecution'];
    $events[EcaEvents::AFTER_INITIAL_EXECUTION][] = ['onAfterInitialExecution'];
    return $events;
  }

}
