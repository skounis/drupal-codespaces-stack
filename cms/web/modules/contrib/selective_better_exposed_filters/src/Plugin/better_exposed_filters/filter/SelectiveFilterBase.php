<?php

namespace Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\options\Plugin\views\filter\ListField;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\search_api\Plugin\views\filter\SearchApiOptions;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTidDepth;
use Drupal\verf\Plugin\views\filter\EntityReference as VERF;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\views\Plugin\views\filter\EntityReference;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Base class for Better exposed filters widget plugins.
 */
abstract class SelectiveFilterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration(): array {
    return [
      'options_show_only_used' => FALSE,
      'options_show_only_used_filtered' => FALSE,
      'options_hide_when_empty' => FALSE,
      'options_show_items_count' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function buildConfigurationForm(FilterPluginBase $filter, array $settings): array {
    $form = [];
    if ($filter->isExposed() && (
      $filter instanceof TaxonomyIndexTid
      || $filter instanceof EntityReference
      || $filter instanceof SearchApiOptions
      || $filter instanceof Bundle
      || $filter instanceof VERF
      || $filter instanceof ListField
      ) && !($filter instanceof TaxonomyIndexTidDepth)
    ) {
      $form['options_show_only_used'] = [
        '#type' => 'checkbox',
        '#title' => t('Show only used items'),
        '#default_value' => !empty($settings['options_show_only_used']),
        '#description' => t('Restrict exposed filter values to those presented in the result set.'),
      ];

      $form['options_show_only_used_filtered'] = [
        '#type' => 'checkbox',
        '#title' => t('Filter items based on filtered result set by other applied filters'),
        '#default_value' => !empty($settings['options_show_only_used_filtered']),
        '#description' => t('Restrict exposed filter values to those presented in the already filtered result set by other applied filters. (Not work when view use ajax form in block)'),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][options_show_only_used]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['options_hide_when_empty'] = [
        '#type' => 'checkbox',
        '#title' => t('Hide filter, if no options'),
        '#default_value' => !empty($settings['options_hide_when_empty']),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][options_show_only_used]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['options_show_items_count'] = [
        '#type' => 'checkbox',
        '#title' => t('Show option items count'),
        '#description' => t('Show the number of items that will be filtered by each option. Instead of hiding it completely.'),
        '#default_value' => !empty($settings['options_show_items_count']),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][options_show_only_used]"]' => [
              'checked' => TRUE
            ],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function exposedFormAlter(ViewExecutable &$current_view, FilterPluginBase $filter, array $settings, array &$form, FormStateInterface &$form_state): void {
    if ($filter->isExposed() && !empty($settings['options_show_only_used'])) {
      $identifier = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

      // If request not from this function.
      if (empty($current_view->selective_filter)) {
        /** @var \Drupal\views\ViewExecutable $view */
        $view = Views::getView($current_view->id());
        $view->selective_filter = TRUE;
        $view->setArguments($current_view->args);

        if (
          !empty($current_view->display_handler->getOption('exposed_block'))
          && !empty($current_view->argument)
        ) {
          $args = \Drupal::routeMatch()->getRawParameters()->all();
          if (
            !empty($args['view_id'])
            && !empty($args['display_id'])
            && $args['view_id'] === $current_view->id()
            && $args['display_id'] === $current_view->current_display
          ) {
            $view->setArguments($args);
          }
        }

        $view->setDisplay($current_view->current_display);
        $view->preExecute();

        if (!empty($view->display_handler->getPlugin('exposed_form')->options['bef']['general']['input_required'])) {
          $view->display_handler->getPlugin('exposed_form')->options['bef']['general']['input_required'] = FALSE;
        }

        // Include all results of a view, ignoring items_per_page that
        // are set in view itself or in one of `views_pre_view` hooks,
        // which are executed in `$view->preExecute()`.
        $view->setItemsPerPage(0);

        // Query parameters can override default results.
        // Save original query and replace with one without parameters.
        $query_param = $view->getRequest()->query;
        $query_param_orig = clone $query_param;
        // Disable per page param.
        $query_param->remove('items_per_page');

        // Unset exposed filters values.
        if (!empty($settings['options_show_only_used_filtered'])) {
          // Unset current filter value from input to avoid only one option,
          // in other way current filter value will restrict himself.
          if ($query_param->has($identifier)) {
            $query_param->remove($identifier);
          }
        }
        else {
          // In this case we need to skip all filled values for full result.
          foreach ($query_param->keys() as $key) {
            $query_param->remove($key);
          }
        }

        // Set modified query
        $view->getRequest()->query = $query_param;

        // Execute modified query.
        $view->execute();

        // Restore parameters for main query.
        $view->getRequest()->query = $query_param_orig;

        $element = &$form[$identifier];
        if (!empty($view->result)) {
          $hierarchy = !empty($filter->options['hierarchy']);
          $relationship = $filter->options['relationship'];

          if (in_array(SearchApiFilterTrait::class, class_uses($filter)) || $filter instanceof Bundle) {
            $field_id = $filter->options['field'];

            // For Search API fields find original property path:
            if (in_array(SearchApiFilterTrait::class, class_uses($filter))) {
              $index_fields = $view->getQuery()->getIndex()->getFields();
              if (isset($index_fields[$field_id])) {
                $field_id = $index_fields[$field_id]->getPropertyPath();
              }
            }
          }
          else {
            $field_id = $filter->definition['field_name'] ?? NULL;
          }

          $ids = [];
          $relationship_count = [];

          // Avoid illegal choice.
          $user_value = $form_state->getUserInput()[$identifier] ?? NULL;
          if (isset($user_value)) {
            if (is_array($user_value)) {
              $ids = $user_value;
            }
            else {
              $ids[$user_value] = $user_value;
            }
          }

          foreach ($view->result as $row) {
            $entity = $row->_entity;
            if ($relationship != 'none') {
              $entity = $row->_relationship_entities[$relationship] ?? FALSE;
            }
            // Get entity from object.
            if (!isset($entity)) {
              $entity = $row->_object->getEntity();
            }
            if ($entity instanceof TranslatableInterface
              && isset($row->node_field_data_langcode)
              && $entity->hasTranslation($row->node_field_data_langcode)) {
              $entity = $entity->getTranslation($row->node_field_data_langcode);
            }
            if ($field_id && $entity instanceof FieldableEntityInterface && $entity->hasField($field_id)) {
              $item_values = $entity->get($field_id)->getValue();

              if (!empty($item_values)) {
                foreach ($item_values as $item_value) {
                  if (isset($item_value['target_id'])) {
                    $id = $item_value['target_id'];
                    $relationship_count[$id] = isset($relationship_count[$id]) ? $relationship_count[$id] + 1 : 1;
                    $ids[$id] = $id;

                    if ($hierarchy) {
                      $parents = \Drupal::service('entity_type.manager')
                        ->getStorage("taxonomy_term")
                        ->loadAllParents($id);

                      /** @var \Drupal\taxonomy\TermInterface $term */
                      foreach ($parents as $term) {
                        $ids[$term->id()] = $term->id();
                        $relationship_count[$term->id()] = isset($relationship_count[$term->id()]) ? $relationship_count[$term->id()] + 1 : 1;
                      }
                    }
                  }
                  elseif (isset($item_value['value'])) {
                    $id = $item_value['value'];
                    $ids[$id] = $id;
                    $relationship_count[$id] = isset($relationship_count[$id]) ? $relationship_count[$id] + 1 : 1;
                  }
                }
              }
            }
          }

          if (!empty($element['#options'])) {
            foreach ($element['#options'] as $key => $option) {
              if ($key === 'All') {
                continue;
              }

              $target_id = $key;
              if (is_object($option) && !empty($option->option)) {
                $target_id = array_keys($option->option);
                $target_id = reset($target_id);
              }
              if (!in_array($target_id, $ids)) {
                unset($element['#options'][$key]);
              }
              elseif (!empty($settings['options_show_items_count'])) {
                $count = $relationship_count[$target_id] ?? 0;
                $element['#options'][$key] =  $element['#options'][$key] . ' (' . $count . ')';
              }
            }
            // Make the element size fit with the new number of options.
            if (isset($element['#size'])) {
              if (count($element['#options']) >= 2 && count($element['#options']) < $element['#size']) {
                $element['#size'] = count($element['#options']);
              }
            }

            if (
              !empty($settings['options_hide_when_empty'])
              && (
                (count($element['#options']) == 1 && isset($element['#options']['All']))
                || empty($element['#options'])
              )
            ) {
              $element['#access'] = FALSE;
            }
          }
        }
        elseif (!empty($settings['options_hide_when_empty'])) {
          $element['#access'] = FALSE;
        }
      }
    }
  }

}
