<?php

namespace Drupal\eca_views\Plugin\views\access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides ECA based access control.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "eca",
 *   title = @Translation("ECA"),
 *   help = @Translation("Access will be granted by an ECA model.")
 * )
 */
class Eca extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * The trigger event service.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->triggerEvent = $container->get('eca.trigger_event');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    $result = AccessResult::forbidden("No ECA configuration set an access result");
    $event = $this->triggerEvent->dispatchFromPlugin('eca_views:access', $this->view, $account);
    if ($event instanceof AccessEventInterface) {
      $result = $event->getAccessResult();
    }
    return $result === NULL ? FALSE : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {}

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}
