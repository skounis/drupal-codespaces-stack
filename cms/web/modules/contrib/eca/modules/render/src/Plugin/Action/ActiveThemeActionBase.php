<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for actions related to the active theme.
 */
abstract class ActiveThemeActionBase extends ConfigurableActionBase {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The theme initialization service.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected ThemeInitializationInterface $themeInitialization;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeManager = $container->get('theme.manager');
    $instance->themeHandler = $container->get('theme_handler');
    $instance->themeInitialization = $container->get('theme.initialization');
    return $instance;
  }

  /**
   * Helper callback that always returns FALSE.
   *
   * Some machine name fields cannot have a check whether they are already in
   * use. For these elements, this method can be used.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function alwaysFalse(): bool {
    return FALSE;
  }

}
