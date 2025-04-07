<?php

namespace Drupal\simple_search_form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\simple_search_form\Form\SimpleSearchForm;

/**
 * Service for build a simple search form.
 */
class SimpleSearchFormLazyBuilder implements TrustedCallbackInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new SimpleSearchFormLazyBuilder object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['getForm'];
  }

  /**
   * Lazy builder callback to build the form.
   *
   * @param string $config
   *   Simple search form configuration JSON.
   *
   * @return array
   *   A render-able form array.
   */
  public function getForm($config) {
    $config = Json::decode($config);
    $form = $this->formBuilder->getForm(SimpleSearchForm::class, $config);

    // Vary caching of this block per selected $_GET parameter when decided
    // to use "Keep value in search input after form submit" feature.
    if ($config['input_keep_value']) {
      $form['#cache']['contexts'][] = 'url.query_args:' . $config['get_parameter'];
    }
    // Vary caching of this block per $_GET parameters which should be
    // preserved to use "Preserve query parameters from URL" feature.
    if ($config['preserve_url_query_parameters']) {
      foreach ($config['preserve_url_query_parameters'] as $name) {
        $form['#cache']['contexts'][] = 'url.query_args:' . $name;
      }
    }

    return $form;
  }

}
