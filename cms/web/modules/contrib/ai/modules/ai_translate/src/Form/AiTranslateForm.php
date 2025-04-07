<?php

namespace Drupal\ai_translate\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the class for Ai Translate Form.
 */
class AiTranslateForm extends FormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_translate.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   */
  final public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, AiProviderPluginManager $provider_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $build = NULL): ?array {
    _ai_translate_check_default_provider_and_model();
    $form_state->set('entity', $build['#entity']);

    $overview = NULL;

    // If our build has a table in it, we'll assume it is the section of the
    // page we want to update.
    foreach (Element::children($build) as $child) {
      if (isset($build[$child]['#theme']) && $build[$child]['#theme'] == 'table') {
        $overview = $build[$child];
      }
    }

    if ($overview) {

      $form['#title'] = $this->t('Translations of @title', ['@title' => $build['#entity']->label()]);

      // Inject our additional column into the header.
      array_splice($overview['#header'], -1, 0, [$this->t('AI Translations')]);

      // Make this a tableselect form.
      $form['languages'] = [
        '#type' => 'tableselect',
        '#header' => $overview['#header'],
        '#options' => [],
      ];
      $languages = $this->languageManager->getLanguages();

      $entity = $build['#entity'];
      $entity_id = $entity->id();
      $entity_type_id = $entity->getEntityTypeId();
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $default_revision = $storage->load($entity_id);
      $entity_type = $entity->getEntityType();

      $use_latest_revisions = $entity_type->isRevisionable() && ContentTranslationManager::isPendingRevisionSupportEnabled($entity_type_id, $entity->bundle());
      $lang_from = $entity->getUntranslated()->language()->getId();

      $config = $this->config(static::CONFIG_NAME);

      foreach ($languages as $langcode => $language) {
        $option = array_shift($overview['#rows']);

        // Get the latest revision.
        // This logic comes from web/core/modules/content_translation/src/Controller/ContentTranslationController.php.
        if ($use_latest_revisions) {
          $entity = $default_revision;
          $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode);
          if ($latest_revision_id) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
            $latest_revision = $storage->loadRevision($latest_revision_id);
            // Make sure we do not list removed translations, i.e. translations
            // that have been part of a default revision but no longer are.
            if (!$latest_revision->wasDefaultRevision() || $default_revision->hasTranslation($langcode)) {
              $entity = $latest_revision;
            }
          }
        }

        $ai_model = FALSE;
        $additional = '';
        if ($lang_from !== $langcode && !$entity->hasTranslation($langcode)) {
          $model = $config->get($langcode . '_model') ?? '';
          $parts = explode('__', $model);
          if ($model == "" || empty($parts[0])) {
            $default_model = $this->providerManager->getSimpleDefaultProviderOptions('chat');
            if ($default_model == "") {
            }
            else {
              $parts1 = explode('__', $default_model);
              $ai_model = $parts1[1];
            }
          }
          else {
            $ai_model = $parts[1];
          }
          if ($ai_model) {
            $additional = Link::createFromRoute($this->t('Translate using @ai', ['@ai' => $ai_model]),
            'ai_translate.translate_content', [
              'entity_type' => $entity_type_id,
              'entity_id' => $entity_id,
              'lang_from' => $lang_from,
              'lang_to' => $langcode,
            ]
            )->toString();
          }
        }
        else {
          $additional = $this->t('NA');
        }

        // Inject the additional column into the array.
        // The generated form structure has changed, support both an additional
        // 'data' key (that is not supported by tableselect) and the old version
        // without.
        if (isset($option['data'])) {
          array_splice($option['data'], -1, 0, [$additional]);
          // Append the current option array to the form.
          $form['languages']['#options'][$langcode] = $option['data'];
        }
        else {
          array_splice($option, -1, 0, [$additional]);
          // Append the current option array to the form.
          $form['languages']['#options'][$langcode] = $option;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Function to call the translate API and get the result.
   */
  public function aiTranslateResult(array &$form, FormStateInterface $form_state) {
    return [];
  }

}
