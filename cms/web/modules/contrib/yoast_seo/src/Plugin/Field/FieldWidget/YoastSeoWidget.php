<?php

namespace Drupal\yoast_seo\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\yoast_seo\Form\AnalysisFormHandler;
use Drupal\yoast_seo\SeoManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced widget for yoast_seo field.
 *
 * @FieldWidget(
 *   id = "yoast_seo_widget",
 *   label = @Translation("Real-time SEO form"),
 *   field_types = {
 *     "yoast_seo"
 *   }
 * )
 */
class YoastSeoWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Instance of YoastSeoManager service.
   *
   * @var \Drupal\yoast_seo\SeoManager
   */
  protected $seoManager;

  /**
   * The Drupal theme manager.
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The yoast_seo.settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Target elements for Javascript.
   *
   * @var array
   */
  public static $jsTargets = [
    'wrapper_target_id'       => 'yoast-wrapper',
    'snippet_target_id'       => 'yoast-snippet',
    'output_target_id'        => 'yoast-output',
    'overall_score_target_id' => 'yoast-overall-score',
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('yoast_seo.manager'),
      $container->get("theme_handler"),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, SeoManager $manager, ThemeHandlerInterface $theme_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->seoManager = $manager;
    $this->themeHandler = $theme_handler;
    $this->config = $config_factory->get('yoast_seo.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $form_state->set('yoast_settings', $this->getSettings());

    // Create the form element.
    $element += [
      '#type' => 'details',
      '#open' => TRUE,
      '#attached' => [
        'library' => [
          'yoast_seo/yoast_seo_core',
          'yoast_seo/yoast_seo_admin',
        ],
      ],
    ];

    $element['focus_keyword'] = [
      '#id' => Html::getUniqueId('yoast_seo-' . $delta . '-focus_keyword'),
      '#type' => 'textfield',
      '#title' => $this->t('Focus keyword'),
      '#default_value' => $items[$delta]->focus_keyword ?? NULL,
      '#description' => $this->t("Pick the main keyword or keyphrase that this post/page is about."),
    ];

    $element['overall_score'] = [
      '#theme' => 'overall_score',
      '#overall_score_target_id' => self::$jsTargets['overall_score_target_id'],
      '#overall_score' => $this->seoManager->getScoreStatus($items[$delta]->status ?? 0),
    ];

    $element['status'] = [
      '#id' => Html::getUniqueId('yoast_seo-' . $delta . '-status'),
      '#type' => 'hidden',
      '#title' => $this->t('Real-time SEO status'),
      '#default_value' => $items[$delta]->status ?? NULL,
      '#description' => $this->t("The SEO status in points."),
    ];

    // Snippet.
    $element['snippet_analysis'] = [
      '#theme' => 'yoast_snippet',
      '#wrapper_target_id' => self::$jsTargets['wrapper_target_id'],
      '#snippet_target_id' => self::$jsTargets['snippet_target_id'],
      '#output_target_id' => self::$jsTargets['output_target_id'],
    ];

    $js_config = $this->getJavaScriptConfiguration();

    $js_config['fields']['focus_keyword'] = $element['focus_keyword']['#id'];
    $js_config['fields']['seo_status'] = $element['status']['#id'];

    // Add fields to store editable properties.
    foreach (['title', 'description'] as $property) {
      if ($this->getSetting('edit_' . $property)) {
        $element['edit_' . $property] = [
          '#id' => Html::getUniqueId('yoast_seo-' . $delta . '-' . $property),
          '#type' => 'hidden',
          '#default_value' => $items[$delta]->{$property} ?? NULL,
        ];
        $js_config['fields']['edit_' . $property] = $element['edit_' . $property]['#id'];
      }
    }

    $form_object = $form_state->getFormObject();

    if ($form_object instanceof EntityForm) {
      $js_config['is_new'] = $form_object->getEntity()->isNew();
    }
    else {
      // If we aren't working with an entity we assume whatever we are working
      // with is new.
      $js_config['is_new'] = TRUE;
    }

    $element['#attached']['drupalSettings']['yoast_seo'] = $js_config;

    // Add analysis submit button.
    $target_type = $this->fieldDefinition->getTargetEntityTypeId();
    if ($this->entityTypeManager->hasHandler($target_type, 'yoast_seo_preview_form')) {
      $form_handler = $this->entityTypeManager->getHandler($target_type, 'yoast_seo_preview_form');

      if ($form_handler instanceof AnalysisFormHandler) {
        $form_handler->addAnalysisSubmit($element, $form_state);
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $value['title'] = ($this->getSetting('edit_title') ? $value['edit_title'] : NULL);
      $value['description'] = ($this->getSetting('edit_description') ? $value['edit_description'] : NULL);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'edit_title' => FALSE,
      'edit_description' => FALSE,
      'render_theme' => NULL,
      'render_view_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['edit_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable title editing'),
      '#description' => $this->t('When this is checked the page title will be editable through the Real-Time SEO widget.'),
      '#default_value' => $this->getSetting('edit_title'),
    ];

    $form['edit_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable description editing'),
      '#description' => $this->t('When this is checked the meta description will be editable through the Real-Time SEO widget.'),
      '#default_value' => $this->getSetting('edit_description'),
    ];

    $form['render_theme'] = [
      '#type' => 'select',
      '#title' => $this->t("Analysis theme"),
      '#description' => $this->t("The theme that the preview will be rendered in. This may affect analysis results."),
      '#options' => array_map(
        fn (Extension $theme) => $theme->info['name'],
        $this->themeHandler->listInfo()
      ),
      '#default_value' => $this->getSetting('render_theme'),
    ];

    $form['render_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t("Analysis view mode"),
      '#description' => $this->t("The view mode that the preview will be rendered in. This may affect analysis results."),
      '#options' => array_reduce(
        $this->entityTypeManager->getStorage('entity_view_display')
          ->loadByProperties([
            'targetEntityType' => $this->fieldDefinition->getTargetEntityTypeId(),
            'bundle' => $this->fieldDefinition->getTargetBundle(),
          ]),
        fn (array $options, EntityViewDisplayInterface $display) => [
          ...$options,
          $display->getMode() => $display->getMode(),
        ],
        []
      ),
      '#default_value' => $this->getSetting('render_view_mode'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('edit_title')) {
      $summary[] = 'Title editing enabled';
    }

    if ($this->getSetting('edit_description')) {
      $summary[] = 'Description editing enabled';
    }

    $render_theme = $this->getSetting("render_theme");
    if ($render_theme !== NULL) {
      $summary[] = 'Analysing in theme: ' . $this->themeHandler->getName($render_theme);
    }

    $summary[] = 'Analysing view mode: ' . $this->getSetting("render_view_mode");

    return $summary;
  }

  /**
   * Returns the JavaScript configuration for this widget.
   *
   * @return array
   *   The configuration that should be attached for the module to work.
   */
  protected function getJavaScriptConfiguration() {
    global $base_root;
    $score_rules = $this->seoManager->getScoreRules();

    // @todo Use dependency injection for language manager.
    // @todo Translate to something usable by YoastSEO.js.
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $configuration = [
      // Set localization within the YoastSEO.js library.
      'language' => $language,
      // Set the base for URL analysis.
      'base_root' => $base_root,
      // Set up score to indicator word rules.
      'score_rules' => $score_rules,
      // Possibly allow properties to be editable.
      'enable_editing' => [],
      // Set the auto refresh seo result status.
      'auto_refresh_seo_result' => $this->config->get('auto_refresh_seo_result'),
    ];

    foreach (['title', 'description'] as $property) {
      $configuration['enable_editing'][$property] = $this->getSetting('edit_' . $property);
    }

    // Set up the names of the text outputs.
    foreach (self::$jsTargets as $js_target_name => $js_target_id) {
      $configuration['targets'][$js_target_name] = $js_target_id;
    }

    return $configuration;
  }

}
