<?php

namespace Drupal\yoast_seo;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\metatag\MetatagManagerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides a preview renderer for entities.
 *
 * @package Drupal\yoast_seo
 */
class EntityAnalyser {

  /**
   * De Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal content renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManagerInterface
   */
  protected $metatagManager;

  /**
   * The Drupal router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * The Drupal theme manager.
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * Theme initialization logic.
   */
  protected ThemeInitializationInterface $themeInitialization;

  /**
   * The default theme used for viewing content.
   */
  protected string $defaultTheme;

  /**
   * Constructs a new EntityPreviewer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   A Drupal Entity renderer.
   * @param \Drupal\metatag\MetatagManagerInterface $metatag_manager
   *   The service for retrieving metatag data.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   A non-access checking router.
   * @param \Drupal\Core\Theme\ThemeManagerInterface|null $theme_manager
   *   The Drupal theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface|null $theme_initialization
   *   The Drupal theme initializer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   The Drupal config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    MetatagManagerInterface $metatag_manager,
    RouterInterface $router,
    ?ThemeManagerInterface $theme_manager = NULL,
    ?ThemeInitializationInterface $theme_initialization = NULL,
    ?ConfigFactoryInterface $configFactory = NULL
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->metatagManager = $metatag_manager;
    $this->router = $router;
    $this->themeManager = $theme_manager ?? \Drupal::service('theme.manager');
    $this->themeInitialization = $theme_initialization ?? \Drupal::service('theme.initialization');
    $this->defaultTheme = ($configFactory ?? \Drupal::configFactory())->get("system.theme")->get('default');
  }

  /**
   * Takes an entity, renders it and adds the metatag values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to retrieve preview data for.
   * @param string|null $theme
   *   The theme to use for the preview or the site default theme if NULL.
   * @param string $view_mode
   *   The view mode to use for the preview, 'full' by default.
   *
   * @return array
   *   An array containing the metatag values. Additionally the url is added if
   *   available under the `url` key and `text` contains a representation of the
   *   rendered HTML.
   */
  public function createEntityPreview(EntityInterface $entity, ?string $theme = NULL, string $view_mode = 'full') {
    // Nodes want to know when they're being previewed.
    if (property_exists($entity, "in_preview")) {
      $entity->in_preview = TRUE;
    }

    // Dealing with a non-renderable entity. When configuring a field.
    if (!$this->entityTypeManager->hasHandler($entity->getEntityTypeId(), 'view_builder')) {
      return [];
    }

    $html = $this->renderEntity($entity, $theme, $view_mode);

    $metatags = $entity instanceof ContentEntityInterface ? $this->metatagManager->tagsFromEntityWithDefaults($entity) : [];

    // Trigger hook_metatags_alter().
    // Allow modules to override tags or the entity used for token replacements.
    // Also used to override editable titles and descriptions.
    $context = [
      'entity' => $entity,
    ];
    \Drupal::service('module_handler')->alter('metatags', $metatags, $context);

    $this->replaceContextAwareTokens($metatags, $entity);

    // Resolve the metatags from tokens into actual values.
    $data = $this->metatagManager->generateRawElements($metatags, $entity);

    // Turn our tag render array into a key => value array.
    foreach ($data as $name => $tag) {
      if (isset($tag['#attributes']['content'])) {
        $data[$name] = $tag['#attributes']['content'];
      }
      elseif (isset($tag['#attributes']['href'])) {
        $data[$name] = $tag['#attributes']['href'];
      }
    }
    // Translate some fields that have different names between metatag module
    // and the Yoast library.
    foreach ($this->getFieldMappings() as $source => $target) {
      if (isset($data[$source])) {
        $data[$target] = $data[$source];
        unset($data[$source]);
      }
    }

    // Add fields that our widget displays.
    $data['title'] = $entity->label();
    // An entity must be saved before it has a URL.
    $data['url'] = !$entity->isNew() ? $entity->toUrl()->toString() : '';

    // Add our HTML as analyzable text (Yoast will sanitize).
    // Newlines are removed because the Yoast library will use them to find
    // paragraph boundaries. However, we output HTML from Drupal which already
    // contains properly formatted paragraphs. Besides, whitespace is
    // meaningless within the context of HTML.
    $data['text'] = str_replace("\n", "", $html->__toString());

    return $data;
  }

  /**
   * Takes an entity and renders it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   * @param string|null $theme
   *   The theme to use for the preview or the site default theme if NULL.
   * @param string $view_mode
   *   The view mode to use for the preview, 'full' by default.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup that represents the rendered entity.
   */
  public function renderEntity(EntityInterface $entity, ?string $theme = NULL, string $view_mode = 'full') {
    $type = $entity->getEntityTypeId();
    $view_builder = $this->entityTypeManager->getViewBuilder($type);
    $render_array = $view_builder->view($entity, $view_mode);

    $active_theme = $this->themeManager->getActiveTheme();
    $analyse_theme = $this->themeInitialization->getActiveThemeByName($theme ?? $this->defaultTheme);

    $this->themeManager->setActiveTheme($analyse_theme);
    $rendered = $this->renderer->renderRoot($render_array);
    $this->themeManager->setActiveTheme($active_theme);

    return $rendered;
  }

  /**
   * Replace context aware tokens in a metatags array.
   *
   * Replaces context aware tokens in a metatags with an entity specific
   * version. This causes things like [current-page:title] to show the entity
   * page title instead of the entity create/edit form title.
   *
   * @param array $metatags
   *   The metatags array that contains the tokens.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to use as context.
   */
  protected function replaceContextAwareTokens(array &$metatags, EntityInterface $entity) {
    foreach ($metatags as $tag => $value) {
      $metatags[$tag] = str_replace('[current-page:title]', $entity->label() ?? '', $value);
      // URL metatags cause issues for new nodes as they don't have a URL yet.
      if ($entity->isNew() && preg_match('/[.\-_:]url/', $value)) {
        $metatags[$tag] = '';
      }
    }
  }

  /**
   * Returns an array of mappings from metatag to Yoast.
   *
   * @return array
   *   The array containing keys that correspond to metatag names and values
   *   that map to the yoast expected names.
   */
  protected function getFieldMappings() {
    return [
      'title' => 'metaTitle',
      'description' => 'meta',
    ];
  }

}
