<?php

namespace Drupal\sitemap\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class VocabularySitemapDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * A module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      foreach ($this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple() as $id => $vocabulary) {
        /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
        $this->derivatives[$id] = $base_plugin_definition;
        $this->derivatives[$id]['title'] = $this->t('Vocabulary: @vocabulary', ['@vocabulary' => $vocabulary->label()]);
        $this->derivatives[$id]['description'] = $vocabulary->getDescription();
        $this->derivatives[$id]['settings']['title'] = NULL;
        $this->derivatives[$id]['vocabulary'] = $vocabulary->id();
        $this->derivatives[$id]['config_dependencies']['config'] = [$vocabulary->getConfigDependencyName()];
      }
    }
    return $this->derivatives;
  }

}
