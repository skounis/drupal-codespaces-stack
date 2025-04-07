<?php

namespace Drupal\scheduler\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Datetime\Plugin\Field\FieldWidget\TimestampDatetimeWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'datetime timestamp' widget.
 *
 * @FieldWidget(
 *   id = "datetime_timestamp_no_default",
 *   label = @Translation("Datetime Timestamp for Scheduler"),
 *   description = @Translation("An optional datetime field. Does not fill in the current datetime if left blank. Defined by Scheduler module."),
 *   field_types = {
 *     "timestamp",
 *   }
 * )
 */
class TimestampDatetimeNoDefaultWidget extends TimestampDatetimeWidget {

  /**
   * The Scheduler settings config object.
   *
   * @var \Drupal\Core\Config\Scheduler
   */
  protected $schedulerSettings;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    // Trailing comma is incompatible with PHPUnit 9.6.19 in Drupal 9.5 PHP 7.4.
    // phpcs:ignore Drupal.Functions.MultiLineFunctionDeclaration.MissingTrailingComma
    $scheduler_settings
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->schedulerSettings = $scheduler_settings;
  }

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
      $container->get('config.factory')->get('scheduler.settings'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // The default description "Format: html-date html-time. Leave blank to use
    // the time of form submission" is inherited from TimestampDatetimeWidget,
    // but this is entirely replaced in _scheduler_entity_form_alter().
    // However this widget is generic and may be used elsewhere, so provide
    // an accurate #description here.
    $element['value']['#description'] = $this->t('Leave blank for no date.');

    // Set the callback function to allow interception of the submitted user
    // input and add the default time if needed. It is too late to try this in
    // function massageFormValues as the validation has already been done.
    $element['value']['#value_callback'] = [$this, 'valueCallback'];

    // Hide the seconds portion of the time input element if that option is set.
    if ($this->schedulerSettings->get('hide_seconds')) {
      $element['value']['#date_increment'] = 60;
      // Some browsers HTML5 date element implementations show the seconds on
      // pre-existing date values event though the number cannot be changed. To
      // reduce confusion set the seconds to zero so that the browsers
      // validation messages only have hours and minutes.
      $current_value = $element['value']['#default_value'];
      if (is_object($current_value)) {
        $current_value->setTime($current_value->format('H'), $current_value->format('i'), 0);
      }
    }

    return $element;
  }

  /**
   * Callback function to add default time to the input date if needed.
   *
   * This will intercept the user input before form validation is processed.
   * However, if the field is 'required' then the browser validation may have
   * already failed before this point. The solution is to pre-fill the time
   * using javascript - see js/scheduler_default_time.js. But that cannot be
   * done when the date is not 'required' hence do the processing here too.
   */
  public function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      $date_input = $element['#date_date_element'] != 'none' && !empty($input['date']) ? $input['date'] : '';
      $time_input = $element['#date_time_element'] != 'none' && !empty($input['time']) ? $input['time'] : '';
      // If there is an input date but no time and the date-only option is on
      // then set the input time to the default specified by scheduler options.
      if (!empty($date_input) && empty($time_input) && $this->schedulerSettings->get('allow_date_only')) {
        $input['time'] = $this->schedulerSettings->get('default_time');
      }
    }

    // Temporarily set the #date_time_element to 'time' because if it had been
    // hidden in the form by being set to 'none' then the default time set above
    // would not be used and we would get the current hour and minute instead.
    $originalTimeElement = $element['#date_time_element'];
    $element['#date_time_element'] = 'time';
    // Chain on to the standard valueCallback for Datetime as we do not want to
    // duplicate that core code here.
    $value = Datetime::valueCallback($element, $input, $form_state);
    // Restore the #date_time_element.
    $element['#date_time_element'] = $originalTimeElement;

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      // @todo The structure is different whether access is denied or not, to
      //   be fixed in core issue https://www.drupal.org/node/2326533.
      if (isset($item['value']) && $item['value'] instanceof DrupalDateTime) {
        $date = $item['value'];
      }
      elseif (isset($item['value']['object']) && $item['value']['object'] instanceof DrupalDateTime) {
        $date = $item['value']['object'];
      }
      else {
        // The above is copied from core Datetime/Plugin/Field/FieldWidget
        // TimestampDatetimeWidget. But here is where we do not return a current
        // datetime when no value is sent in the form.
        $date = NULL;
      }

      $item['value'] = $date ? $date->getTimestamp() : NULL;
    }
    return $values;
  }

}
