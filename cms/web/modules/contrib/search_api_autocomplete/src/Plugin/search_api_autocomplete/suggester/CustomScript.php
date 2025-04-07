<?php

namespace Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;

/**
 * Uses a custom (non-Drupal) script for generating autocomplete suggestions.
 *
 * @see custom_autocomplete_script.php
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "custom_script",
 *   label = @Translation("Use custom script"),
 *   description = @Translation("Specify the path to a PHP script file (or Drupal route) which should be used to generate autocomplete suggestions. This can be used to completely bypass Drupal for improved performance.<br />(<strong>Caution:</strong> If a non-Drupal script is used, Drupal's access restrictions will also be bypassed.)<br />(Note: Due to the nature of this suggester, some of the other settings for this search will be ignored (including other enabled suggesters).)"),
 * )
 */
class CustomScript extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function supportsSearch(SearchInterface $search) {
    return (bool) \Drupal::config('search_api_autocomplete.settings')
      ->get('enable_custom_scripts');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'path' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom script path'),
      '#description' => $this->t('The internal path or external URL to use for autocompletion. A local path should start with a leading slash. For using an external URL, take CSRF protection into account.'),
      '#default_value' => $this->configuration['path'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('path', '') === '') {
      $args = ['%field' => $form['path']['#title']];
      $message = $this->t('The %field field is required.', $args);
      $form_state->setError($form['path'], $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterAutocompleteElement(array &$element) {
    $options['query'] = $element['#autocomplete_route_parameters'];
    unset($element['#autocomplete_route_name'], $element['#autocomplete_route_parameters']);

    $path = $this->configuration['path'];
    $path_len = strlen($path);
    // We allow both internal and external URLs to be used.
    if ($path_len > 1
        && $path[0] === '/'
        && ($path_len < 2 || $path[1] !== '/')) {
      $url = Url::fromUserInput($path, $options);
    }
    else {
      $url = Url::fromUri($path, $options);
    }
    $url = $url->toString(TRUE);

    // Without the "#autocomplete_route_name", we need to inline
    // \Drupal\Core\Render\Element\FormElement::processAutocomplete().
    $element['#attributes']['class'][] = 'form-autocomplete';
    // Provide a data attribute for the JavaScript behavior to bind to.
    $element['#attributes']['data-autocomplete-path'] = $url->getGeneratedUrl();

    $metadata = BubbleableMetadata::createFromRenderArray($element);
    $metadata->merge($url)
      ->applyTo($element);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    // Autocomplete suggestions are created elsewhere if this plugin is used.
    return [];
  }

}
