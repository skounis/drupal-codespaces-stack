<?php

namespace Drupal\search_api_autocomplete\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Search\SearchPluginInterface;
use Drupal\search_api_autocomplete\Search\SearchPluginManager;
use Drupal\search_api_autocomplete\Utility\PluginHelperInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the overview of all search autocompletion configurations.
 */
class IndexOverviewForm extends FormBase {

  /**
   * The autocomplete suggester manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $suggesterManager;

  /**
   * The search plugin manager.
   *
   * @var \Drupal\search_api_autocomplete\Search\SearchPluginManager
   */
  protected $searchPluginManager;

  /**
   * The plugin helper.
   *
   * @var \Drupal\search_api_autocomplete\Utility\PluginHelperInterface
   */
  protected $pluginHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Creates a new AutocompleteSearchAdminOverview instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $suggester_manager
   *   The suggester manager.
   * @param \Drupal\search_api_autocomplete\Search\SearchPluginManager $search_plugin_manager
   *   The search plugin manager.
   * @param \Drupal\search_api_autocomplete\Utility\PluginHelperInterface $plugin_helper
   *   The plugin helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(PluginManagerInterface $suggester_manager, SearchPluginManager $search_plugin_manager, PluginHelperInterface $plugin_helper, EntityTypeManagerInterface $entity_type_manager, RedirectDestinationInterface $redirect_destination, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    $this->suggesterManager = $suggester_manager;
    $this->searchPluginManager = $search_plugin_manager;
    $this->pluginHelper = $plugin_helper;
    $this->entityTypeManager = $entity_type_manager;
    $this->redirectDestination = $redirect_destination;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.search_api_autocomplete.suggester'),
      $container->get('plugin.manager.search_api_autocomplete.search'),
      $container->get('search_api_autocomplete.plugin_helper'),
      $container->get('entity_type.manager'),
      $container->get('redirect.destination'),
      $container->get('messenger'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_autocomplete_admin_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?IndexInterface $search_api_index = NULL) {
    try {
      // Display hint about using the new Search API Solr Autocomplete module.
      // See search_api_autocomplete_requirements().
      if (interface_exists(SolrBackendInterface::class)
          && $search_api_index->hasValidServer()
          && $search_api_index->getServerInstance()->getBackend() instanceof SolrBackendInterface
          && !$this->moduleHandler->moduleExists('search_api_solr_autocomplete')
          && (method_exists(SolrBackendInterface::class, 'getPreferredSchemaVersion')
              || version_compare(SolrBackendInterface::SEARCH_API_SOLR_SCHEMA_VERSION, '4.2.5', '>='))
      ) {
        $this->messenger->addWarning($this->t('When using a Solr server as the search backend, it is recommended to enable the "Search API Solr Autocomplete" module for improved autocomplete functionality.'));
      }
    }
    catch (SearchApiException $e) {
      // Ignore here, will cause enough problems elsewhere.
    }

    $form_state->set('index', $search_api_index);
    $index_id = $search_api_index->id();

    $form['#tree'] = TRUE;
    $form['#title'] = $this->t('Manage autocomplete for search index %label', ['%label' => $search_api_index->label()]);

    /** @var \Drupal\search_api_autocomplete\SearchInterface[] $searches_by_plugin */
    $searches_by_plugin = [];
    foreach ($this->loadAutocompleteSearchByIndex($index_id) as $search) {
      $searches_by_plugin[$search->getSearchPluginId()] = $search;
    }
    $used_ids = [];

    $plugins = $this->pluginHelper->createSearchPluginsForIndex($index_id);
    foreach ($plugins as $plugin_id => $plugin) {
      $group_label = (string) $plugin->getGroupLabel();
      if (empty($form[$group_label])) {
        $form[$group_label] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $plugin->getGroupLabel(),
        ];
        if ($description = $plugin->getGroupDescription()) {
          $form[$group_label]['#description'] = '<p>' . $description . '</p>';
        }
        $form[$group_label]['searches']['#type'] = 'tableselect';
        $form[$group_label]['searches']['#header'] = [
          'label' => $this->t('Label'),
          'operations' => $this->t('Operations'),
        ];
        $form[$group_label]['searches']['#empty'] = '';
        $form[$group_label]['searches']['#js_select'] = TRUE;
      }

      if (isset($searches_by_plugin[$plugin_id])) {
        $search = $searches_by_plugin[$plugin_id];
      }
      else {
        $search = $this->createSearchForPlugin($plugin, $used_ids);
        $used_ids[$search->id()] = TRUE;
      }

      $id = $search->id();
      $form_state->set(['searches', $id], $search);
      $form[$group_label]['searches'][$id] = [
        '#type' => 'checkbox',
        '#default_value' => $search->status(),
        '#parents' => ['searches', $id],
      ];

      $options = &$form[$group_label]['searches']['#options'][$id];
      $options['label'] = $search->label();
      $items = [];
      if ($search->status()) {
        $items[] = [
          'title' => $this->t('Edit'),
          'url' => $search->toUrl('edit-form'),
        ];
      }
      if (!$search->isNew()) {
        $items[] = [
          'title' => $this->t('Delete'),
          'url' => $search->toUrl('delete-form'),
        ];
      }

      if ($items) {
        $options['operations'] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $items,
          ],
        ];
      }
      else {
        $options['operations'] = '';
      }
      unset($options);
    }

    if (!Element::children($form)) {
      $form['message']['#markup'] = '<p>' . $this->t('There are currently no searches known for this index. You need to create at least one search view.') . '</p>';
    }
    else {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save'),
      ];
    }

    return $form;
  }

  /**
   * Creates a new search entity for the given search plugin.
   *
   * @param \Drupal\search_api_autocomplete\Search\SearchPluginInterface $plugin
   *   The search plugin for which to create a search entity.
   * @param true[] $used_ids
   *   The IDs of the searches created so far for this form (to avoid
   *   duplicates). IDs are used as array keys, mapping to TRUE.
   *
   * @return \Drupal\search_api_autocomplete\SearchInterface
   *   The new search entity.
   */
  protected function createSearchForPlugin(SearchPluginInterface $plugin, array $used_ids) {
    $plugin_id = $plugin->getPluginId();
    $index_id = $plugin->getIndexId();

    $id = $base_id = $plugin->getDerivativeId() ?: $plugin_id;
    $search_storage = $this->entityTypeManager
      ->getStorage('search_api_autocomplete_search');
    $i = 0;
    while (!empty($used_ids[$id]) || $search_storage->load($id)) {
      $id = $base_id . '_' . ++$i;
    }

    $search = Search::create([
      'id' => $id,
      'status' => FALSE,
      'label' => $plugin->label(),
      'index_id' => $index_id,
      'search_settings' => [
        $plugin_id => [],
      ],
      'options' => [],
    ]);

    // Find a supported suggester and set it as the default for the search.
    $suggesters = array_map(function ($suggester_info) {
      return $suggester_info['class'];
    }, $this->suggesterManager->getDefinitions());
    $filter_suggesters = function ($suggester_class) use ($search) {
      return $suggester_class::supportsSearch($search);
    };
    $available_suggesters = array_filter($suggesters, $filter_suggesters);
    if ($available_suggesters) {
      reset($available_suggesters);
      $suggester_id = key($available_suggesters);
      $suggester = $this->pluginHelper
        ->createSuggesterPlugin($search, $suggester_id);
      $search->setSuggesters([
        $suggester_id => $suggester,
      ]);
    }

    return $search;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messages = $this->t('The settings have been saved.');
    foreach ($form_state->getValue('searches') as $id => $enabled) {
      /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
      $search = $form_state->get(['searches', $id]);
      if ($search && $search->status() != $enabled) {
        $change = TRUE;
        if ($search->isNew()) {
          $options['query'] = $this->redirectDestination->getAsArray();
          $options['fragment'] = 'module-search_api_autocomplete';
          $url = Url::fromRoute('user.admin_permissions', [], $options);
          if ($url->access()) {
            $vars[':perm_url'] = $url->toString();
            $messages = $this->t('The settings have been saved. Remember to set the <a href=":perm_url">permissions</a> for the newly enabled searches.', $vars);
          }
        }
        $search->setStatus($enabled);
        $search->save();
      }
    }
    $this->messenger->addStatus(empty($change) ? $this->t('No values were changed.') : $messages);
  }

  /**
   * Load the autocomplete plugins for an index.
   *
   * @param string $index_id
   *   The index ID.
   *
   * @return \Drupal\search_api_autocomplete\SearchInterface[]
   *   An array of autocomplete plugins.
   */
  protected function loadAutocompleteSearchByIndex($index_id) {
    /** @var \Drupal\search_api_autocomplete\SearchInterface[] $searches */
    $searches = $this->entityTypeManager
      ->getStorage('search_api_autocomplete_search')
      ->loadByProperties([
        'index_id' => $index_id,
      ]);
    return $searches;
  }

}
