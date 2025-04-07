<?php

namespace Drupal\search_api_autocomplete\Controller;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api_autocomplete\SearchApiAutocompleteException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Utility\AutocompleteHelperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Provides a controller for autocompletion.
 */
class AutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Creates a new AutocompleteController instance.
   *
   * @param \Drupal\search_api_autocomplete\Utility\AutocompleteHelperInterface $autocompleteHelper
   *   The autocomplete helper service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The transliterator.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected AutocompleteHelperInterface $autocompleteHelper,
    protected RendererInterface $renderer,
    protected TransliterationInterface $transliterator,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.search_api_autocomplete');
    return new static(
      $container->get('search_api_autocomplete.helper'),
      $container->get('renderer'),
      $container->get('transliteration'),
      $logger,
    );
  }

  /**
   * Page callback: Retrieves autocomplete suggestions.
   *
   * @param \Drupal\search_api_autocomplete\SearchInterface $search_api_autocomplete_search
   *   The search for which to retrieve autocomplete suggestions.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The autocompletion response.
   */
  public function autocomplete(SearchInterface $search_api_autocomplete_search, Request $request) {
    $matches = [];
    $search = $search_api_autocomplete_search;

    if (!$search->status() || !$search->hasValidIndex()) {
      return new JsonResponse($matches);
    }

    try {
      $keys = $request->query->get('q');
      if ("$keys" === '') {
        return new JsonResponse($matches);
      }
      [$complete, $incomplete] = $this->autocompleteHelper->splitKeys($keys);
      $data = $request->query->all();
      unset($data['q']);
      $query = $search->createQuery($complete, $data);
      if (!$query) {
        return new JsonResponse($matches);
      }

      // Prepare the query.
      $query->setSearchId('search_api_autocomplete:' . $search->id());
      $query->addTag('search_api_autocomplete');

      // Get total limit and per-suggester limits.
      $limit = $search->getOption('limit');
      $suggester_limits = $search->getSuggesterLimits();

      // Get all enabled suggesters, ordered by weight.
      $suggesters = $search->getSuggesters();
      $suggester_weights = $search->getSuggesterWeights();
      $suggester_weights = array_intersect_key($suggester_weights, $suggesters);
      $suggester_weights += array_fill_keys(array_keys($suggesters), 0);
      asort($suggester_weights);

      /** @var \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[] $suggestions */
      $suggestions = [];
      // Go through all enabled suggesters in order of increasing weight and
      // add their suggestions (until the limit is reached).
      foreach (array_keys($suggester_weights) as $suggester_id) {
        // Clone the query in case the suggester makes any modifications.
        $tmp_query = clone $query;

        // Compute the applicable limit based on (remaining) total limit and
        // the suggester-specific limit (if set).
        if (isset($suggester_limits[$suggester_id])) {
          $suggester_limit = min($limit, $suggester_limits[$suggester_id]);
        }
        else {
          $suggester_limit = $limit;
        }
        $tmp_query->range(0, $suggester_limit);

        // Add suggestions in a loop so we're sure they're numerically
        // indexed.
        $new_suggestions = $suggesters[$suggester_id]
          ->getAutocompleteSuggestions($tmp_query, $incomplete, $keys);
        foreach ($new_suggestions as $suggestion) {
          $suggestions[] = $suggestion;

          if (--$limit == 0) {
            // If we've reached the limit, stop adding suggestions.
            break 2;
          }
        }
      }

      // Allow other modules to alter the created suggestions.
      $alter_params = [
        'query' => $query,
        'search' => $search,
        'incomplete_key' => $incomplete,
        'user_input' => $keys,
      ];
      $this->moduleHandler()
        ->alter('search_api_autocomplete_suggestions', $suggestions, $alter_params);

      // Then, add the suggestions to the $matches return array in the form
      // expected by Drupal's autocomplete Javascript.
      $show_count = $search->getOption('show_count');
      foreach ($suggestions as $suggestion) {
        // If "Display result count estimates" was disabled, remove the
        // count from the suggestion.
        if (!$show_count) {
          $suggestion->setResultsCount(NULL);
        }
        $build = $suggestion->toRenderable();
        if ($build) {
          // Render the label.
          try {
            $label = DeprecationHelper::backwardsCompatibleCall(
              \Drupal::VERSION,
              '10.3.0',
              fn () => $this->renderer->renderInIsolation($build),
              fn () => $this->renderer->renderPlain($build),
            );
          }
          catch (\Exception $e) {
            Error::logException($this->logger, $e, '%type while rendering an autocomplete suggestion: @message in %function (line %line of %file).');
            continue;
          }

          // Decide what the action of the suggestion is – entering specific
          // search terms or redirecting to a URL.
          if ($suggestion->getUrl()) {
            // Generate an HTML-free version of the label to use as the value.
            // Setting the label as the value here is necessary for proper
            // accessibility via screen readers (which will otherwise read the
            // URL).
            $url = $suggestion->getUrl()->toString();
            $trimmed_label = trim(strip_tags((string) $label)) ?: $url;
            $matches[] = [
              'value' => $trimmed_label,
              'url' => $url,
              'label' => $label,
            ];
          }
          else {
            $matches[] = [
              'value' => trim($suggestion->getSuggestedKeys()),
              'label' => $label,
            ];
          }
        }
      }
    }
    catch (SearchApiAutocompleteException $e) {
      Error::logException($this->logger, $e, '%type while retrieving autocomplete suggestions: @message in %function (line %line of %file).');
    }

    return new JsonResponse($matches);
  }

}
