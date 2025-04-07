<?php

namespace Drupal\sitemap\Plugin\Sitemap;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\sitemap\SitemapBase;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a sitemap for an taxonomy vocabulary.
 *
 * @Sitemap(
 *   id = "vocabulary",
 *   title = @Translation("Vocabulary"),
 *   description = @Translation("Vocabulary description"),
 *   settings = {
 *     "title" = NULL,
 *     "show_description" = FALSE,
 *     "show_count" = FALSE,
 *     "display_unpublished" = FALSE,
 *     "term_depth" = 9,
 *     "term_count_threshold" = 0,
 *     "customize_link" = FALSE,
 *     "term_link" = "entity.taxonomy_term.canonical|taxonomy_term",
 *     "always_link" = FALSE,
 *     "enable_rss" = FALSE,
 *     "rss_link" = "view.taxonomy_term.feed_1|arg_0",
 *     "rss_depth" = 9,
 *   },
 *   deriver = "Drupal\sitemap\Plugin\Derivative\VocabularySitemapDeriver",
 *   enabled = FALSE,
 *   vocabulary = "",
 * )
 */
class Vocabulary extends SitemapBase {

  /**
   * The maximum depth that may be configured for taxonomy terms.
   *
   * @var int
   */
  const DEPTH_MAX = 9;

  /**
   * The term depth value that equates to the setting being disabled.
   *
   * @var int
   */
  const DEPTH_DISABLED = 0;

  /**
   * The threshold count value that equates to the setting being disabled.
   *
   *  @var int
   */
  const THRESHOLD_DISABLED = 0;

  /**
   * The default taxonomy term route|arg.
   *
   * @var string
   */
  const DEFAULT_TERM_LINK = 'entity.taxonomy_term.canonical|taxonomy_term';

  /**
   * The default taxonomy term RSS feed route|arg.
   *
   * @var string
   */
  const DEFAULT_TERM_RSS_LINK = 'view.taxonomy_term.feed_1|arg_0';

  /**
   * A configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
   * A route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->routeProvider = $container->get('router.route_provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Provide the menu name as the default title.
    $vid = $this->getPluginDefinition()['vocabulary'];
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
    $form['title']['#default_value'] = $this->settings['title'] ?? $vocab->label();

    $form['show_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display vocabulary description'),
      '#default_value' => $this->settings['show_description'],
      '#description' => $this->t('When enabled, this option will show the vocabulary description.'),
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display node counts next to taxonomy terms'),
      '#default_value' => $this->settings['show_count'],
      '#description' => $this->t('When enabled, this option will show the number of nodes in each taxonomy term.'),
    ];

    $form['display_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display unpublished taxonomy terms'),
      '#default_value' => $this->settings['display_unpublished'] ?? FALSE,
      '#description' => $this->t('When enabled, this option will include unpublished taxonomy terms.<br><strong>Warning</strong>: displaying unpublished taxonomy terms will reveal information that would normally require the sitemap viewer to have the %permission permission!', [
        '%permission' => $this->t('Administer vocabularies and terms'),
      ]),
      '#access' => $this->currentUser->hasPermission('show unpublished taxonomy terms on sitemap'),
    ];

    $form['term_depth'] = [
      // @todo Number type not submitting?
      // '#type' = 'number',
      '#type' => 'textfield',
      '#title' => $this->t('Term depth'),
      '#default_value' => $this->settings['term_depth'],
      // '#min' => self::DEPTH_DISABLED,
      // '#max' => self::DEPTH_MAX,
      '#size' => 3,
      '#description' => $this->t(
        'Specify how many levels of taxonomy terms should be included. For instance, enter <code>1</code> to only include top-level terms, or <code>@disabled</code> to include no terms. The maximum depth is <code>@max</code>.',
        ['@disabled' => self::DEPTH_DISABLED, '@max' => self::DEPTH_MAX]
      ),
    ];

    $form['term_count_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Term count threshold'),
      '#default_value' => $this->settings['term_count_threshold'],
      '#size' => 3,
      '#description' => $this->t(
        'Only show taxonomy terms whose node counts are greater than or equal to this threshold. Set to <em>@disabled</em> to disable this threshold. Note that in hierarchical taxonomies, parent items with children will still be shown.',
        ['@disabled' => self::THRESHOLD_DISABLED]
      ),
    ];

    $form['customize_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Customize term links'),
      '#default_value' => $this->settings['customize_link'],
    ];
    $customizeLinkName = 'plugins[vocabulary:' . $vid . '][settings][customize_link]';

    $form['term_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Term link route and argument'),
      '#default_value' => $this->settings['term_link'],
      "#description" => $this->t('Provide the route name and route argument name for the link, in the from of <code>route.name|argument.name</code>. The default value of this field is <code>entity.taxonomy_term.canonical|taxonomy_term</code>.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $customizeLinkName . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['always_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always link to the taxonomy term.'),
      '#default_value' => $this->settings['always_link'],
      '#description' => $this->t('There are a few cases where a taxonomy term maybe be displayed in the list, but will not have a link created (for example, terms without any tagged content [nodes], or parent terms displayed when the threshold is greater than zero). Check this box to ensure that a link to the taxonomy term is always provided.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $customizeLinkName . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['enable_rss'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable RSS feed links'),
      '#default_value' => $this->settings['enable_rss'],
    ];
    $enableRssName = 'plugins[vocabulary:' . $vid . '][settings][enable_rss]';

    $form['rss_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSS route and argument'),
      '#default_value' => $this->settings['rss_link'],
      "#description" => $this->t('Provide the route name and route argument name for the link, in the from of <code>route.name|argument.name</code>. The default value of this field is <code>view.taxonomy_term.feed_1|arg_0</code>.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $enableRssName . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rss_depth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSS depth'),
      '#default_value' => $this->settings['rss_depth'],
      '#size' => 3,
      '#maxlength' => 10,
      '#description' => $this->t(
        'Specify how many levels of taxonomy terms should have a link to the default RSS feed included. For instance, enter <code>1</code> to include an RSS feed for the top-level terms, or <code>@disabled</code> to not include a feed. The maximum depth is <code>@max</code>.',
        ['@disabled' => self::DEPTH_DISABLED, '@max' => self::DEPTH_MAX]
      ),
      '#states' => [
        'visible' => [
          ':input[name="' . $enableRssName . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $vid = $this->pluginDefinition['vocabulary'];
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
    $content = [];

    if (isset($this->settings['show_description']) && $this->settings['show_description']) {
      $content[] = ['#markup' => $vocabulary->getDescription()];
    }

    // Plan for a nested list of terms.
    $display_unpublished = $this->settings['display_unpublished'];
    $list = [];
    if ($maxDepth = $this->settings['term_depth']) {
      /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

      $hierarchyType = $termStorage->getVocabularyHierarchyType($vid);
      // Fetch the top-level terms.
      $terms = $termStorage->loadTree($vid, 0, 1);
      // We might not need to worry about the vocabulary being nested.
      if ($hierarchyType == VocabularyInterface::HIERARCHY_DISABLED || $maxDepth == 1) {
        foreach ($terms as $term) {
          if (!$display_unpublished && empty($term->status)) {
            continue;
          }
          $term->treeDepth = $term->depth;
          if ($display = $this->buildSitemapTerm($term)) {
            $list[$term->tid]['data'] = $display;
          }
        }
      }
      elseif ($hierarchyType == VocabularyInterface::HIERARCHY_SINGLE) {
        // Use a more structured tree to create a nested list.
        foreach ($terms as $obj) {
          if (!$display_unpublished && empty($obj->status)) {
            continue;
          }
          $currentDepth = 1;
          $this->buildList($list, $obj, $vid, $currentDepth, $maxDepth, $display_unpublished);
          // @todo Remove parents where all child terms are not displayed.
        }
      }
      else {
        // @todo Support multiple hierarchy? Need to test.
      }
    }

    // @todo Test & Document
    // Add an alter hook for modules to manipulate the taxonomy term output.
    $this->moduleHandler->alter([
      'sitemap_vocabulary', 'sitemap_vocabulary_' . $vid,
    ], $list, $vid);

    $content[] = [
      '#theme' => 'item_list',
      '#items' => $list,
    ];

    return ($list) ? [
      '#theme' => 'sitemap_item',
      '#title' => $this->settings['title'],
      '#content' => $content,
      '#sitemap' => $this,
      // @todo Does a vocabulary cache tag exist?
    ] : [];
  }

  /**
   * Builds a taxonomy term item.
   *
   * @param object $term
   *   The term object returned by TermStorage::loadTree()
   *
   * @return array|void
   *   Returns an array if the term display is TRUE.
   */
  protected function buildSitemapTerm($term) {
    $this->checkTermThreshold($term);

    if ($term->display) {
      return [
        '#theme' => 'sitemap_taxonomy_term',
        '#name' => $term->name,
        '#url' => $this->buildTermLink($term) ?: '',
        '#show_link' => $this->determineLinkVisibility($term),
        '#show_count' => $this->determineCountVisibility($term),
        '#count' => $term->count ?? '',
        '#show_feed' => $this->settings['enable_rss'],
        '#feed' => $this->buildFeedLink($term) ?: '',
      ];
    }
  }

  /**
   * Checks the threshold count for a term.
   *
   * @param object $term
   *   The term object returned by TermStorage::loadTree()
   */
  protected function checkTermThreshold(&$term) {
    if (!isset($term->display)) {
      $term->display = FALSE;
    }
    $threshold = $this->settings['term_count_threshold'];
    $showCount = $this->settings['show_count'];
    $term->count = sitemap_taxonomy_term_count_nodes($term->tid);
    if ($threshold || $showCount) {
      if ($threshold && !isset($term->hasChildren)) {
        if ($term->count >= $threshold) {
          $term->display = TRUE;
        }
      }
      else {
        $term->display = TRUE;
      }
    }
    else {
      $term->display = TRUE;
    }
  }

  /**
   * Builds the taxonomy term link.
   *
   * @param object $term
   *   The term object returned by TermStorage::loadTree()
   *
   * @return string|void
   *   Returns the link.
   */
  protected function buildTermLink($term) {
    $vid = $this->pluginDefinition['vocabulary'];
    // @todo Add and test handling for Forum vs Vocab routes
    if ($this->moduleHandler->moduleExists('forum') && $vid == $this->configFactory->get('forum.settings')->get('vocabulary')) {
      return Url::fromRoute('forum.index')->toString();
    }

    // Route validation will be provided on form save and config update,
    // rather than every time a link is created.
    if (isset($this->settings['term_link'])) {
      return $this->buildLink($this->settings['term_link'], $term->tid);
    }
  }

  /**
   * Builds the taxonomy term feed link.
   *
   * @param object $term
   *   The term object returned by TermStorage::loadTree()
   *
   * @return string|void
   *   Returns the link builded.
   */
  protected function buildFeedLink($term) {
    $rssDepth = $this->settings['rss_depth'];
    if ($rssDepth && isset($term->treeDepth) && $rssDepth >= $term->treeDepth) {
      // Route validation will be provided on form save and config update,
      // rather than every time a link is created.
      if ($this->settings['enable_rss'] && !empty($this->settings['rss_link'])) {
        return $this->buildLink($this->settings['rss_link'], $term->tid);
      }
    }
  }

  /**
   * Builds a tree/list array given a taxonomy term tree object.
   *
   * @param array $list
   *   The list of terms.
   * @param object $object
   *   The term object.
   * @param string $vid
   *   The vocabulary id.
   * @param int $currentDepth
   *   The current depth.
   * @param int $maxDepth
   *   The max depth.
   * @param bool $display_unpublished
   *   Check publish/unpublish from configuration.
   *
   * @see https://www.webomelette.com/loading-taxonomy-terms-tree-drupal-8
   */
  protected function buildList(array &$list, $object, $vid, &$currentDepth, $maxDepth, $display_unpublished = FALSE) {
    // Check that we are only working with the parent-most term.
    if ($object->depth != 0) {
      return;
    }

    // Track current depth of the term.
    $object->treeDepth = $currentDepth;

    // Check for children on the term.
    // @todo Implement $termStorage at the class level.
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $children = $termStorage->loadTree($vid, $object->tid, 1);
    if (!$children) {
      $object->hasChildren = FALSE;
      if ($element = $this->buildSitemapTerm($object)) {
        $list[$object->tid][] = $element;
      }
      return;
    }
    else {
      // If the term has children, it should always be displayed.
      // @todo That's not entirely accurate...
      $object->display = TRUE;
      $object->hasChildren = TRUE;
      $list[$object->tid][] = $this->buildSitemapTerm($object);
      $list[$object->tid]['children'] = [];
      $object_children = &$list[$object->tid]['children'];
    }
    $currentDepth++;

    if ($maxDepth >= $currentDepth) {
      /** @var \Drupal\taxonomy\TermInterface $child */
      foreach ($children as $child) {
        if (!$display_unpublished && empty($child->status)) {
          continue;
        }
        $this->buildList($object_children, $child, $vid, $currentDepth, $maxDepth, $display_unpublished);
      }
    }
  }

  /**
   * Determine whether the link for a term should be displayed.
   *
   * @param object $term
   *   The term object.
   *
   * @return bool
   *   Returns if the link should be displayed or not.
   */
  protected function determineLinkVisibility($term) {
    if ($this->settings['always_link']) {
      return TRUE;
    }
    elseif ($this->settings['term_count_threshold'] == Vocabulary::THRESHOLD_DISABLED && $term->count) {
      return TRUE;
    }
    elseif ($this->settings['term_count_threshold'] && $term->count >= $this->settings['term_count_threshold']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determine whether the usage count for a term should be displayed.
   *
   * @param object $term
   *   The term object.
   *
   * @return bool
   *   Returns if the usage count for a term should be displayed.
   */
  protected function determineCountVisibility($term) {
    if ($this->settings['show_count']) {
      if ($threshold = $this->settings['term_count_threshold']) {
        if ($term->count >= $threshold) {
          return TRUE;
        }
      }
      else {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Build the URL given a route|arg pattern.
   *
   * @param string $string
   *   The link.
   * @param int $tid
   *   The term id.
   *
   * @return string
   *   Returns the url builded.
   */
  protected function buildLink($string, $tid) {
    $parts = $this->splitRouteArg($string);
    return Url::fromRoute($parts['route'], [$parts['arg'] => $tid])->toString();
  }

  /**
   * Helper function to split the route|arg pattern.
   *
   * @param string $string
   *   The string that will be split.
   *
   * @return array
   *   Returns the route|arg pattern.
   */
  protected function splitRouteArg($string) {
    $return = [];

    if ($string) {
      $arr = explode('|', $string);
      if (count($arr) == 2) {
        $return['route'] = $arr[0];
        $return['arg'] = $arr[1];
      }
    }

    return $return;
  }

  /**
   * Validate the route and argument provided.
   *
   * @todo Implement for form_save and config import.
   *
   * @param string $string
   *   The string used to validate the route.
   */
  protected function validateCustomRoute($string) {
    $parts = $this->splitRouteArg($string);

    try {
      $this->routeProvider->getRouteByName($parts['route']);
      // @todo Determine if $route has the provided $parts['arg'] parameter.
    }
    catch (\Exception $e) {
      // @todo .
    }
  }

}
