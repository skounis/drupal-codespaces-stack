<?php

namespace Drupal\search_api_autocomplete_test\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Defines a test suggester class.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "search_api_autocomplete_test",
 *   label = @Translation("Test suggester"),
 *   description = @Translation("Suggester used for tests."),
 * )
 */
class TestSuggester extends SuggesterPluginBase implements PluginFormInterface {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public static function supportsSearch(SearchInterface $search) {
    $key = 'search_api_test.suggester.method.' . __FUNCTION__;
    $override = \Drupal::state()->get($key);
    if ($override) {
      return call_user_func($override, $search);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    if ($override = $this->getMethodOverride(__FUNCTION__)) {
      return call_user_func($override, $this, $form, $form_state);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    if ($override = $this->getMethodOverride(__FUNCTION__)) {
      call_user_func($override, $this, $form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    if ($override = $this->getMethodOverride(__FUNCTION__)) {
      call_user_func($override, $this, $form, $form_state);
      return;
    }
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    if ($override = $this->getMethodOverride(__FUNCTION__)) {
      return call_user_func($override, $this, $query, $incomplete_key, $user_input);
    }

    $suggestions = [];
    $factory = new SuggestionFactory($user_input);

    // Add some suffix-based suggestions and one URL-based suggestion.
    for ($i = 1; $i < $query->getOption('limit', 10); ++$i) {
      $suggestions[] = $factory->createFromSuggestionSuffix("-suggester-$i", $i);
    }

    $account = \Drupal::currentUser()->getAccount();
    if ($uid = $account->id()) {
      $url = Url::fromRoute('entity.user.canonical', ['user' => $uid]);
    }
    else {
      $url = Url::fromRoute('user.login');
    }
    $suggestions[] = $factory->createUrlSuggestion($url, "$user_input-suggester-url");

    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->logMethodCall(__FUNCTION__, func_get_args());
    if ($override = $this->getMethodOverride(__FUNCTION__)) {
      return call_user_func($override, $this);
    }
    if (!empty($this->configuration['dependencies'])) {
      return $this->configuration['dependencies'];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      unset($this->configuration['dependencies']);
    }
    return $remove;
  }

}
