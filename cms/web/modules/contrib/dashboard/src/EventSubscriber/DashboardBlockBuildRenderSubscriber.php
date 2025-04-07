<?php

declare(strict_types=1);

namespace Drupal\dashboard\EventSubscriber;

use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects #dashboard into blocks on render.
 */
final class DashboardBlockBuildRenderSubscriber implements EventSubscriberInterface {

  /**
   * Inject dashboard into blocks on render.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $contexts = $event->getContexts();
    if (!isset($contexts['dashboard'])
      || !$contexts['dashboard']->getContextData()->getEntity()
      || 'dashboard' != $contexts['dashboard']->getContextData()->getEntity()->getEntityTypeId()) {
      return;
    }
    $build = $event->getBuild();
    $build['#dashboard'] = $contexts['dashboard']->getContextData()->getEntity()->id();
    $event->setBuild($build);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
      ],
    ];
  }

}
