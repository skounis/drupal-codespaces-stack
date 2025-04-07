<?php

namespace Drupal\search_api_autocomplete\Search;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api_autocomplete\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for search plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. The definition includes the following keys:
 * - id: The unique, system-wide identifier of the search plugin.
 * - label: The human-readable name of the search plugin, translated.
 * - description: A human-readable description for the search plugin,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiAutocompleteSearch(
 *   id = "my_search",
 *   label = @Translation("Custom Search"),
 *   description = @Translation("Custom-defined site-specific search."),
 *   index = "my_index",
 * )
 * @endcode
 *
 * @see \Drupal\search_api_autocomplete\Annotation\SearchApiAutocompleteSearch
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginInterface
 * @see \Drupal\search_api_autocomplete\Search\SearchPluginManager
 * @see plugin_api
 * @see hook_search_api_autocomplete_search_info_alter()
 */
abstract class SearchPluginBase extends PluginBase implements SearchPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setEntityTypeManager($container->get('entity_type.manager'));
    $plugin->setStringTranslation($container->get('string_translation'));

    return $plugin;
  }

  /**
   * Retrieves the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Sets the entity manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupLabel() {
    $plugin_definition = $this->getPluginDefinition();
    if (isset($plugin_definition['group_label'])) {
      return $plugin_definition['group_label'];
    }
    return $this->t('Other searches');
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupDescription() {
    $plugin_definition = $this->getPluginDefinition();
    if (isset($plugin_definition['group_description'])) {
      return $plugin_definition['group_description'];
    }
    return $this->t('Searches not belonging to any specific group');
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexId() {
    return $this->getPluginDefinition()['index'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->getEntityTypeManager()
      ->getStorage('search_api_index')
      ->load($this->getIndexId());
  }

}
