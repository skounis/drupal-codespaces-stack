<?php

namespace Drupal\eca_misc\Plugin\ECA\Event;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\block_content\BlockContentEvents;
use Drupal\block_content\Event\BlockContentGetDependencyEvent;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\locale\LocaleEvent;
use Drupal\locale\LocaleEvents;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for Drupal core.
 *
 * @EcaEvent(
 *   id = "drupal",
 *   deriver = "Drupal\eca_misc\Plugin\ECA\Event\DrupalCoreEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class DrupalCoreEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    $actions = [];
    if (class_exists(BlockContentEvents::class)) {
      $actions['block_content_get_dependency'] = [
        'label' => 'Block content get dependency',
        'event_name' => BlockContentEvents::BLOCK_CONTENT_GET_DEPENDENCY,
        'event_class' => BlockContentGetDependencyEvent::class,
        'description' => new TranslatableMarkup('Fires, when getting the dependency of a non-reusable block.'),
      ];
    }
    if (class_exists(FileUploadSanitizeNameEvent::class)) {
      $actions['file_upload_sanitize_name_event'] = [
        'label' => 'Sanitize file name',
        'event_name' => FileUploadSanitizeNameEvent::class,
        'event_class' => FileUploadSanitizeNameEvent::class,
        'description' => new TranslatableMarkup('Fires during a file upload that lets subscribers sanitize the filename.'),
      ];
    }
    if (class_exists(RenderEvents::class)) {
      $actions['select_page_display_variant'] = [
        'label' => 'Select page display mode',
        'event_name' => RenderEvents::SELECT_PAGE_DISPLAY_VARIANT,
        'event_class' => PageDisplayVariantSelectionEvent::class,
        'description' => new TranslatableMarkup('Fires when selecting a page display variant to use.'),
      ];
    }
    if (class_exists(ResourceTypeBuildEvents::class)) {
      $actions['build'] = [
        'label' => 'Build resource type',
        'event_name' => ResourceTypeBuildEvents::BUILD,
        'event_class' => ResourceTypeBuildEvent::class,
        'description' => new TranslatableMarkup('Fires during the resource type build process.'),
      ];
    }
    if (class_exists(LayoutBuilderEvents::class)) {
      $actions['prepare_layout'] = [
        'label' => 'Prepare layout builder element',
        'event_name' => LayoutBuilderEvents::PREPARE_LAYOUT,
        'event_class' => PrepareLayoutEvent::class,
        'description' => new TranslatableMarkup('Fires, when preparing a layout builder element.'),
      ];
      $actions['section_component_build_render_array'] = [
        'label' => 'Build render array',
        'event_name' => LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY,
        'event_class' => SectionComponentBuildRenderArrayEvent::class,
        'description' => new TranslatableMarkup('Fires, when a render array of a component is built.'),
      ];
    }
    if (class_exists(LocaleEvents::class)) {
      $actions['save_translation'] = [
        'label' => 'Save translated string',
        'event_name' => LocaleEvents::SAVE_TRANSLATION,
        'event_class' => LocaleEvent::class,
        'description' => new TranslatableMarkup('Fires, when saving a translated string.'),
      ];
    }
    $actions['recipe_applied'] = [
      'label' => 'Recipe applied',
      'event_name' => RecipeAppliedEvent::class,
      'event_class' => RecipeAppliedEvent::class,
      'description' => new TranslatableMarkup('Fires, when a recipe has been applied.'),
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    $configuration = $ecaEvent->getConfiguration();
    if ($this->getDerivativeId() === 'recipe_applied') {
      return empty($configuration['recipe_base_path']) ? '*' : $configuration['recipe_base_path'];
    }
    return parent::generateWildcard($eca_config_id, $ecaEvent);
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event instanceof RecipeAppliedEvent) {
      if ($wildcard === '*') {
        return TRUE;
      }
      return basename($event->recipe->path) === $wildcard;
    }
    return parent::appliesForWildcard($event, $event_name, $wildcard);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'recipe_base_path' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['recipe_base_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base path of recipe'),
      '#default_value' => $this->configuration['recipe_base_path'],
      '#description' => $this->t('The base path of the recipe that got applied; e.g. if the recipe is stored in "/var/www/recipe/my_recipe" then the base path is "my_recipe". Leave empty to respond to all recipes.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['recipe_base_path'] = $form_state->getValue('recipe_base_path');
  }

}
