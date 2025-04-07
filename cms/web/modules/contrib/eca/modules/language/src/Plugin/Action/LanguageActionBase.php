<?php

namespace Drupal\eca_language\Plugin\Action;

use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_language\EcaLanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for language-related actions.
 */
abstract class LanguageActionBase extends ConfigurableActionBase {

  /**
   * The decorator of the language manager.
   *
   * @var \Drupal\eca_language\EcaLanguageManager
   */
  protected EcaLanguageManager $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setLanguageManager($container->get('eca_language.manager'));
    return $instance;
  }

  /**
   * Set the language manager.
   *
   * @param \Drupal\eca_language\EcaLanguageManager $manager
   *   The language manager.
   */
  public function setLanguageManager(EcaLanguageManager $manager): void {
    $this->languageManager = $manager;
  }

}
