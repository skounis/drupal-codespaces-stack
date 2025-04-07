<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Contrib\AutocompleteBackendInterface as NewAutocompleteBackendInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\AutocompleteBackendInterface as OldAutocompleteBackendInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a suggester plugin that retrieves suggestions from the server.
 *
 * The server needs to support the "search_api_autocomplete" feature for this to
 * work.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "server",
 *   label = @Translation("Retrieve from server"),
 *   description = @Translation("Make suggestions based on the data indexed on the server."),
 * )
 */
class Server extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|null
   */
  protected $languageManager;

  /**
   * The transliteration.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface|null
   */
  protected $transliterator;

  /**
   * {@inheritdoc}
   */
  public static function supportsSearch(SearchInterface $search) {
    return (bool) static::getBackend($search->getIndex());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setLanguageManager($container->get('language_manager'));
    $plugin->setTransliterator($container->get('transliteration'));

    return $plugin;
  }

  /**
   * Retrieves the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function getLanguageManager() {
    return $this->languageManager ?: \Drupal::service('language_manager');
  }

  /**
   * Sets the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The new language manager.
   *
   * @return $this
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
    return $this;
  }

  /**
   * Retrieves the transliteration.
   *
   * @return \Drupal\Component\Transliteration\TransliterationInterface
   *   The transliteration.
   */
  public function getTransliterator() {
    return $this->transliterator ?: \Drupal::service('transliteration');
  }

  /**
   * Sets the transliteration.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The new transliteration.
   *
   * @return $this
   */
  public function setTransliterator(TransliterationInterface $transliterator) {
    $this->transliterator = $transliterator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Let the user select the fulltext fields to use for autocomplete.
    $search = $this->getSearch();
    $fields = $search->getIndex()->getFields();
    $fulltext_fields = $search->getIndex()->getFulltextFields();
    $options = [];
    foreach ($fulltext_fields as $field) {
      $options[$field] = $fields[$field]->getFieldIdentifier();
    }
    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Override used fields'),
      '#description' => $this->t('Select the fields which should be searched for matches when looking for autocompletion suggestions. Leave blank to use the same fields as the underlying search.'),
      '#options' => $options,
      '#default_value' => array_combine($this->getConfiguration()['fields'], $this->getConfiguration()['fields']),
      '#attributes' => ['class' => ['search-api-checkboxes-list']],
    ];
    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values['fields'] = array_keys(array_filter($values['fields']));
    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    $index = $query->getIndex();
    if (!($backend = static::getBackend($index))) {
      return [];
    }

    // If the "Transliteration" processor is enabled for the search index, we
    // also need to transliterate the user input for autocompletion.
    if ($index->isValidProcessor('transliteration')) {
      $langcode = $this->getLanguageManager()->getCurrentLanguage()->getId();
      $incomplete_key = $this->getTransliterator()->transliterate($incomplete_key, $langcode);
      $user_input = $this->getTransliterator()->transliterate($user_input, $langcode);
    }

    if ($this->configuration['fields']) {
      $query->setFulltextFields($this->configuration['fields']);
    }
    $query->preExecute();
    return $backend->getAutocompleteSuggestions($query, $this->getSearch(), $incomplete_key, $user_input);
  }

  /**
   * Retrieves the backend for the given index, if it supports autocomplete.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\search_api_autocomplete\AutocompleteBackendInterface|null
   *   The backend plugin of the index's server, if it exists and supports
   *   autocomplete; NULL otherwise.
   */
  protected static function getBackend(IndexInterface $index) {
    if (!$index->hasValidServer()) {
      return NULL;
    }
    try {
      $server = $index->getServerInstance();
      $backend = $server->getBackend();
    }
    catch (SearchApiException $e) {
      return NULL;
    }
    if ($server->supportsFeature('search_api_autocomplete')
        || $backend instanceof NewAutocompleteBackendInterface
        || $backend instanceof OldAutocompleteBackendInterface) {
      return $backend;
    }
    return NULL;
  }

}
