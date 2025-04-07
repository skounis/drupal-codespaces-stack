<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Drupal\search_api\Utility\QueryHelperInterface;
use Drupal\search_api_autocomplete\Search\SearchPluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides autocomplete support for the search_api_page module.
 *
 * @SearchApiAutocompleteSearch(
 *   id = "page",
 *   group_label = @Translation("Search pages"),
 *   group_description = @Translation("Searches provided by the <em>Search pages</em> module"),
 *   provider = "search_api_page",
 *   deriver = "Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\search\PageDeriver",
 * )
 */
class Page extends SearchPluginBase implements ContainerFactoryPluginInterface {

  use LoggerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The query helper service.
   *
   * @var \Drupal\search_api\Utility\QueryHelperInterface|null
   */
  protected $queryHelper;

  /**
   * The parse mode manager.
   *
   * @var \Drupal\search_api\ParseMode\ParseModePluginManager|null
   */
  protected $parseModeManager;

  /**
   * Creates a new Page instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );

    $plugin->setQueryHelper($container->get('search_api.query_helper'));
    $plugin->setParseModeManager($container->get('plugin.manager.search_api.parse_mode'));
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.search_api_autocomplete');
    $plugin->setLogger($logger);

    return $plugin;
  }

  /**
   * Retrieves the query helper.
   *
   * @return \Drupal\search_api\Utility\QueryHelperInterface
   *   The query helper.
   */
  public function getQueryHelper() {
    return $this->queryHelper ?: \Drupal::service('search_api.query_helper');
  }

  /**
   * Sets the query helper.
   *
   * @param \Drupal\search_api\Utility\QueryHelperInterface $query_helper
   *   The new query helper.
   *
   * @return $this
   */
  public function setQueryHelper(QueryHelperInterface $query_helper) {
    $this->queryHelper = $query_helper;
    return $this;
  }

  /**
   * Retrieves the parse mode manager.
   *
   * @return \Drupal\search_api\ParseMode\ParseModePluginManager
   *   The parse mode manager.
   */
  public function getParseModeManager(): ParseModePluginManager {
    return $this->parseModeManager ?: \Drupal::service('plugin.manager.search_api.parse_mode');
  }

  /**
   * Sets the parse mode manager.
   *
   * @param \Drupal\search_api\ParseMode\ParseModePluginManager $parse_mode_manager
   *   The new parse mode manager.
   *
   * @return $this
   */
  public function setParseModeManager(ParseModePluginManager $parse_mode_manager): self {
    $this->parseModeManager = $parse_mode_manager;
    return $this;
  }

  /**
   * Retrieves the logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  public function getLogger(): LoggerInterface {
    return $this->logger ?: \Drupal::service('logger.channel.search_api_autocomplete');
  }

  /**
   * {@inheritdoc}
   */
  public function createQuery($keys, array $data = []) {
    $query = $this->getQueryHelper()->createQuery($this->getIndex());
    $page = $this->getPage();
    if ($page && $page->getSearchedFields()) {
      $query->setFulltextFields(array_values($page->getSearchedFields()));
    }
    // Search pages default to using the "direct" parse mode.
    $parse_mode_id = 'direct';
    if ($page && method_exists($page, 'getParseMode')) {
      $parse_mode_id = $page->getParseMode() ?: 'direct';
    }
    if ($parse_mode_id !== $query->getParseMode()->getPluginId()) {
      try {
        /** @var \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode */
        $parse_mode = $this->getParseModeManager()
          ->createInstance($parse_mode_id);
        $query->setParseMode($parse_mode);
      }
      catch (PluginException $e) {
        $this->getLogger()->error('Search page %page specifies an unknown parse mode %parse_mode. Falling back to default for autocomplete.', [
          '%page' => $this->getDerivativeId(),
          '%parse_mode' => $parse_mode_id,
        ]);
      }
    }
    $query->keys($keys);
    return $query;
  }

  /**
   * Retrieves the search page entity for this plugin.
   *
   * @return \Drupal\search_api_page\SearchApiPageInterface|null
   *   The search page, or NULL if it couldn't be loaded.
   */
  protected function getPage() {
    /** @var \Drupal\search_api_page\SearchApiPageInterface $page */
    $page = $this->getEntityTypeManager()
      ->getStorage('search_api_page')
      ->load($this->getDerivativeId());
    return $page;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    $page = $this->getPage();
    if ($page) {
      $key = $page->getConfigDependencyKey();
      $name = $page->getConfigDependencyName();
      $this->addDependency($key, $name);
    }

    return $this->dependencies;
  }

}
