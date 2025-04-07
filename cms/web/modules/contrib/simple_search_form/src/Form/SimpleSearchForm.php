<?php

namespace Drupal\simple_search_form\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * SimpleSearchForm definition.
 */
class SimpleSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $config = []) {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromUserInput($config['action_path'])->toString();
    $form['#token'] = FALSE;

    $form[$config['get_parameter']] = [
      '#type' => $config['input_type'],
      '#title' => $config['input_label'],
      '#title_display' => $config['input_label_display'],
      '#attributes' => [
        'placeholder' => $config['input_placeholder'],
        'class' => explode(' ', $config['input_css_classes']),
      ],
      '#default_value' => $config['input_keep_value'] ? $this->getRequest()->query->get($config['get_parameter']) : '',
    ];

    if ($config['input_type'] === 'search_api_autocomplete') {
      $this->setupSearchApiAutocomplete($form, $config);
    }

    if (!empty($config['preserve_url_query_parameters'])) {
      $query = $this->getRequest()->query->all();
      foreach ($config['preserve_url_query_parameters'] as $name) {
        if (!isset($form[$name]) && isset($query[$name]) && ($value = $query[$name]) !== '') {
          if (is_array($value)) {
            foreach (explode('&', UrlHelper::buildQuery($value, $name)) as $param) {
              list($name, $value) = explode('=', $param);
              $form[rawurldecode($name)] = [
                '#type' => 'hidden',
                '#value' => rawurldecode($value),
              ];
            }
          }
          else {
            $form[$name] = [
              '#type' => 'hidden',
              '#value' => $value,
            ];
          }
        }
      }
    }

    if ($config['submit_display']) {
      $form['actions'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'form-actions',
          ],
        ],
      ];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $config['submit_label'],
        // Prevent op from showing up in the query string.
        '#name' => '',
      ];
    }

    // Remove after fix https://www.drupal.org/project/drupal/issues/1191278.
    $form['#after_build'][] = '::cleanupGetParams';

    return $form;
  }

  /**
   * Form #after_build callback.
   *
   * @param array $form
   *   Form to process.
   *
   * @return array
   *   Processed form.
   */
  public function cleanupGetParams(array $form) {
    // Remove all additional $_GET params from URL.
    $form['form_id']['#access'] = FALSE;
    $form['form_build_id']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do.
  }

  /**
   * Setup Search API Autocomplete for requested form.
   *
   * @param array $form
   *   Form to setup Search API Autocomplete.
   * @param array $config
   *   Block configuration.
   */
  protected function setupSearchApiAutocomplete(array &$form, array $config) {
    $autocomplete_config = $config['search_api_autocomplete'];

    if (!empty($autocomplete_config['arguments'])) {
      $arguments = explode(',', $autocomplete_config['arguments']);
      $arguments = array_map('trim', $arguments);
    }

    // Setup search_api_autocomplete field type, see
    // \Drupal\search_api_autocomplete\Utility\AutocompleteHelper
    // method alterElement() for more details.
    $form[$config['get_parameter']]['#search_id'] = $autocomplete_config['search_id'];
    $form[$config['get_parameter']]['#additional_data'] = [
      'filter' => $autocomplete_config['filter'],
      'display' => $autocomplete_config['display'],
    ];

    // Only pass the arguments when we have them configured.
    if (isset($arguments)) {
      $form[$config['get_parameter']]['#additional_data']['arguments'] = $arguments;
    }
  }

}
