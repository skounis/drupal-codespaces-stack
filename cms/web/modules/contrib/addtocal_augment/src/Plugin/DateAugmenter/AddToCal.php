<?php

namespace Drupal\addtocal_augment\Plugin\DateAugmenter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\date_augmenter\DateAugmenter\DateAugmenterPluginBase;
use Drupal\date_augmenter\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Date Augmenter plugin to inject "Add to calendar" links.
 *
 * @DateAugmenter(
 *   id = "addtocal",
 *   label = @Translation("Add to Calendar Links"),
 *   description = @Translation("Adds links to add an events dates to a user's preferred calendar."),
 *   weight = 0
 * )
 */
class AddToCal extends DateAugmenterPluginBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  use PluginFormTrait;

  /**
   * The separator to be used with iCal.
   *
   * @var string
   */
  public $separator = '%0D%0A';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConfigFactoryInterface $config_factory) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $container->get('string_translation');
    $plugin->setStringTranslation($translation);
    return $plugin;
  }

  /**
   * Builds and returns a render array for the task.
   *
   * @param array $output
   *   The existing render array, to be augmented, passed by reference.
   * @param Drupal\Core\Datetime\DrupalDateTime $start
   *   The object which contains the start time.
   * @param Drupal\Core\Datetime\DrupalDateTime|null $end
   *   The optional object which contains the end time.
   * @param array $options
   *   An array of options to further guide output.
   */
  public function augmentOutput(array &$output, DrupalDateTime $start, ?DrupalDateTime $end = NULL, array $options = []) {
    if ($data = $this->buildLinks($output, $start, $end, $options)) {
      $config = $options['settings'] ?? $this->defaultConfiguration();
      $ical_render = $this->implodeRecursive($this->separator, $data['ical']);
      $outlook_render = $this->implodeRecursive($this->separator, $data['outlook']);
      $google_base = 'https://calendar.google.com/calendar/render?action=TEMPLATE&';
      $google_render = $google_base . http_build_query($data['google']);
      // Generate a unique ID for DOM targeting purposes.
      $id = Html::getUniqueId($data['google']['text']);
      $output['addtocal'] = [
        '#theme' => 'addtocal_links',
        '#label' => $config['label'] ?? $this->t('Add to calendar'),
        '#google' => $google_render,
        '#ical' => $ical_render,
        '#outlook' => $outlook_render,
        '#id' => $id,
        '#icons' => $config['icons'] ?? FALSE,
      ];
      if (isset($config['target']) && ($config['target'] === 'modal')) {
        $output['addtocal']['#theme'] = 'addtocal_links__modal';
        $output['addtocal']['#attached']['library'][] = 'addtocal_augment/modal';
      }
    }

    return [];
  }

  /**
   * This call to DrupalDateTime is isolated for overriding in Unit testing.
   *
   * @see Drupal\Tests\addtocal_augment\Unit\TestAddToCal
   */
  protected function getCurrentDate() {
    return new DrupalDateTime();
  }

  /**
   * Extracts timezone from start time.
   *
   * @param Drupal\Core\Datetime\DrupalDateTime $start
   *   Start time of the event.
   * @param array $config
   *   Plugin configuration.
   *
   * @return string|false
   *   The timezone identifier.
   */
  protected function extractTimezone(DrupalDateTime $start, array $config): string|FALSE {
    $timezone = $start->getTimezone();
    if (empty($timezone)) {
      $tz = $this->configFactory->get('system.date')->get('timezone');
      $timezone = $tz['default'];
    }
    if ($timezone instanceof \DateTimeZone) {
      $timezone = $timezone->getName();
    }

    // Default to ignoring UTC if not specifically configured.
    $ignore = $config['ignore_timezone_if_UTC'] ?? TRUE;
    if ($ignore && $timezone === 'UTC') {
      $timezone = FALSE;
    }

    return $timezone;
  }

  /**
   * Builds a prepared array of data for output.
   *
   * @param array $output
   *   The existing render array, to be augmented.
   * @param Drupal\Core\Datetime\DrupalDateTime $start
   *   The object which contains the start time.
   * @param Drupal\Core\Datetime\DrupalDateTime|null $end
   *   The optional object which contains the end time.
   * @param array $options
   *   An array of options to further guide output.
   */
  public function buildLinks(array $output, DrupalDateTime $start, ?DrupalDateTime $end = NULL, array $options = []) {
    // Use provided settings if they exist, otherwise look for plugin config.
    $config = $options['settings'] ?? $this->defaultConfiguration();
    $retain_spacing = $config['retain_spacing'] ?? FALSE;
    $ellipsis = $config['ellipsis'] ?? FALSE;
    if (empty($config['event_title']) && !isset($options['entity'])) {
      // @todo log some kind of warning that we can't work without the entity
      // or a provided title?
      return;
    }
    $def_format = 'Ymd\\THi00';
    $def_format_z = $def_format . '\\Z';
    $end_fallback = $end ?? $start;

    $now = $this->getCurrentDate();
    // For a recurring date, determine if the last instance is in the past.
    $upcoming_instance = FALSE;
    // @todo Validate that if set, $options['ends'] is DrupalDateTime.
    if (!empty($options['repeats']) && (empty($options['ends']) || $options['ends'] > $now)) {
      $upcoming_instance = TRUE;
    }
    $past_events = $config['past_events'] ?? FALSE;
    if (!$upcoming_instance && $end_fallback < $now && !$past_events) {
      return;
    }
    $entity = $options['entity'] ?? NULL;
    if (!$end) {
      $end = $start;
    }
    $timezone = $this->extractTimezone($start, $config);
    if (isset($options['allday']) && $options['allday']) {
      $start_formatted = $timezone ? $start->format("Ymd", ['timezone' => $timezone]) : $start->format("Ymd");
      $end_formatted = $timezone ? $end->format("Ymd", ['timezone' => $timezone]) : $end->format("Ymd");
      $prefix = ':';
    }
    else {
      $date_format = $def_format;
      if ($timezone) {
        $prefix = ';TZID=' . $timezone . ':';
      }
      else {
        $prefix = ':';
      }
      $start_formatted = $timezone ? $start->format($date_format, ['timezone' => $timezone]) : $start->format($date_format);
      $end_formatted = $timezone ? $end->format($date_format, ['timezone' => $timezone]) : $end->format($date_format);
    }
    if (!empty($config['event_title'])) {
      $label = $this->parseField($config['event_title'], $entity, TRUE);
    }
    else {
      $label = $this->parseField($entity->label(), FALSE, TRUE);
    }
    $description = NULL;
    if (!empty($config['description'])) {
      $description = $this->parseField($config['description'], $entity, TRUE, $retain_spacing);
      $max_length = $config['max_desc'] ?? 60;
      if ($max_length) {
        // @todo Use Smart Trim if available.
        $description = trim(substr($description, 0, $max_length));
        if ($ellipsis) {
          $description .= '...';
        }
      }
    }
    $location = NULL;
    if (!empty($config['location'])) {
      $location = $this->parseField($config['location'], $entity, TRUE);
    }
    $uuid = $entity->uuid() ?? Html::getUniqueId($label);

    // Build output.
    $ical_link = ['data:text/calendar;charset=utf8,BEGIN:VCALENDAR'];
    $ical_link[] = 'PRODID:' . $this->configFactory->get('system.site')->get('name');
    if ($timezone) {
      $offset_from = $start->format('O', ['timezone' => $timezone]);
      $offset_to = $end->format('O', ['timezone' => $timezone]);

      // Timezone must precede VEVENT in iCal format
      // per icalendar.org/iCalendar-RFC-5545/3-6-5-time-zone-component.html .
      $google_link['ctz'] = $timezone;
      $ical_link['tz'][] = 'BEGIN:VTIMEZONE';
      $ical_link['tz'][] = 'TZID:' . $timezone;
      $ical_link['tz'][] = 'BEGIN:STANDARD';
      $ical_link['tz'][] = 'TZOFFSETFROM:' . $offset_from;
      $ical_link['tz'][] = 'TZOFFSETTO:' . $offset_to;
      $ical_link['tz'][] = 'END:STANDARD';
      $ical_link['tz'][] = 'END:VTIMEZONE';
    }
    $ical_link[] = 'VERSION:2.0';
    $ical_link[] = 'BEGIN:VEVENT';
    $ical_link[] = 'UID:' . $uuid;

    // Title.
    $ical_link[] = 'SUMMARY:' . rawurlencode($label);
    $google_link['text'] = $label;

    // Dates.
    // As per RFC 2445 4.8.7.2 the DTSTAMP property must be in UTC.
    $utc = new \DateTimeZone('UTC');
    $now->setTimezone($utc);
    $ical_link[] = 'DTSTAMP:' . $now->format($def_format_z);
    $ical_link['start'] = 'DTSTART' . $prefix . $start_formatted;
    $ical_link['end'] = 'DTEND' . $prefix . $end_formatted;
    $google_link['dates'] = $start_formatted . '/' . $end_formatted;

    // Recurrence.
    if (!empty($options['repeats'])) {
      $ical_link[] = '' . $options['repeats'];
      $google_link['recur'] = $options['repeats'];
    }

    // Description.
    if ($description) {
      $google_link['details'] = $description;
      if ($retain_spacing) {
        // iCalendar linebreaks must be escaped per
        // https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.11.
        $description = str_replace("\r\n", "\\n", $description);
        $description = str_replace("\n", "\\n", $description);
      }
      $ical_link[] = 'DESCRIPTION:' . rawurlencode($description);
    }

    // Location.
    if ($location) {
      $ical_link[] = 'LOCATION:' . rawurlencode($location);
      $google_link['location'] = $location;
    }
    $ical_link[] = 'END:VEVENT';
    $ical_link[] = 'END:VCALENDAR';

    // Set start/end dates timezone to UTC for Outlook.
    $outlook_link = $ical_link;
    if (isset($outlook_link['tz'])) {
      unset($outlook_link['tz']);
    }
    $start->setTimezone($utc);
    $end->setTimezone($utc);
    $outlook_link['start'] = 'DTSTART:' . ($timezone ? $start->format($def_format_z) : $start->format($def_format));
    $outlook_link['end'] = 'DTEND:' . ($timezone ? $end->format($def_format_z) : $end->format($def_format));

    return [
      'ical' => $ical_link,
      'outlook' => $outlook_link,
      'google' => $google_link,
    ];
  }

  /**
   * Manipulate the provided value, checking for tokens and cleaning up.
   *
   * @param string $field_value
   *   The value to manipulate.
   * @param mixed $entity
   *   The entity whose values can be used to replace tokens.
   * @param bool $strip_markup
   *   Whether or not to clean up the output.
   * @param bool $retain_spacing
   *   Whether or not to strip whitespace.
   *
   * @return string
   *   The manipulated value, prepared for use in a link href.
   */
  public function parseField($field_value, $entity, $strip_markup = FALSE, $retain_spacing = FALSE) {
    /* @phpstan-ignore-next-line */
    if (\Drupal::hasService('token') && $entity) {
      /* @phpstan-ignore-next-line */
      $token_service = \Drupal::service('token');
      $token_data = [
        $entity->getEntityTypeId() => $entity,
      ];
      $field_value = $token_service->replace($field_value, $token_data, ['clear' => TRUE]);
    }
    if ($strip_markup) {
      // Strip tags. Requires decoding entities, which will be re-encoded later.
      $field_value = strip_tags(html_entity_decode($field_value));
    }
    if (!$retain_spacing) {
      // Strip line breaks.
      $field_value = preg_replace('/\n|\r|\t/m', ' ', $field_value);
      // Strip non-breaking spaces.
      $field_value = str_replace('&nbsp;', ' ', $field_value);
      $field_value = str_replace("\xc2\xa0", ' ', $field_value);
      // Strip extra spaces.
      $field_value = trim(preg_replace('/\s\s+/', ' ', $field_value));

    }
    return $field_value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'event_title' => '',
      'location' => '',
      'description' => '',
      'retain_spacing' => FALSE,
      'icons' => TRUE,
      'max_desc' => 60,
      'ellipsis' => TRUE,
      'past_events' => FALSE,
      'label' => 'Add to calendar',
      'target' => '',
      'ignore_timezone_if_UTC' => TRUE,
    ];
  }

  /**
   * Create configuration fields for the plugin form, or injected directly.
   *
   * @param array $form
   *   The form array.
   * @param array|null $settings
   *   The setting to use as defaults.
   * @param mixed $field_definition
   *   A parameter to define the field being modified. Likely FieldConfig.
   *
   * @return array
   *   The updated form array.
   */
  public function configurationFields(array $form, ?array $settings, $field_definition = NULL) {
    if (empty($settings)) {
      $settings = $this->defaultConfiguration();
    }
    $form['label'] = [
      '#title' => $this->t('Links label'),
      '#type' => 'textfield',
      '#default_value' => $settings['label'],
      '#description' => $this->t('Text to prefix the actual add links.'),
    ];

    $form['event_title'] = [
      '#title' => $this->t('Event title'),
      '#type' => 'textfield',
      '#default_value' => $settings['event_title'],
      '#description' => $this->t('Optional - if left empty, the entity label will be used. You can use static text or tokens.'),
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#description' => $this->t('Optional. You can use static text or tokens.'),
      '#default_value' => $settings['location'],
    ];

    $form['description'] = [
      '#title' => $this->t('Event description'),
      '#type' => 'textarea',
      '#default_value' => $settings['description'],
      '#description' => $this->t('Optional. You can use static text or tokens.'),
    ];

    $form['retain_spacing'] = [
      '#title' => $this->t('Description: preserve linebreaks and non-breaking spaces.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($settings['retain_spacing']),
      '#description' => $this->t('Preserving line breaks can be helpful when the event description includes multiple lines or Zoom link information with indentation.'),
    ];

    $form['icons'] = [
      '#title' => $this->t('Show icons'),
      '#type' => 'checkbox',
      '#default_value' => !empty($settings['icons']),
      '#description' => $this->t('Show icons instead of text for the label and links.'),
    ];

    $form['max_desc'] = [
      '#title' => $this->t('Maximum description length'),
      '#type' => 'number',
      '#default_value' => $settings['max_desc'] ?? 60,
      '#description' => $this->t('Trim the description to a specified length. Leave empty or use zero to not trim the value.'),
    ];

    $form['ellipsis'] = [
      '#title' => $this->t('Add ellipsis (...) to trimmed descriptions.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($settings['ellipsis']),
    ];

    $form['target'] = [
      '#title' => $this->t('Links display'),
      '#description' => $this->t('Display as a list of links or as a single link that triggers a modal (pop-up).'),
      '#type' => 'select',
      '#default_value' => $settings['target'] ?? '',
      '#options' => [
        '' => $this->t('List of links'),
        'modal' => $this->t('Modal dialog'),
      ],
    ];

    $form['past_events'] = [
      '#title' => $this->t('Show Add to Cal widget for past events?'),
      '#type' => 'checkbox',
      '#default_value' => $settings['past_events'] ?? FALSE,
    ];

    $form['ignore_timezone_if_UTC'] = [
      '#title' => $this->t('Ignore the timezone if it is set to UTC?'),
      '#type' => 'checkbox',
      '#default_value' => $settings['ignore_timezone_if_UTC'] ?? TRUE,
    ];

    if (function_exists('token_theme')) {
      $type = NULL;
      if (method_exists($field_definition, 'getTargetEntityTypeId')) {
        $type = $field_definition->getTargetEntityTypeId();
      }
      // @todo support other field types?
      $form['token_tree_link'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$type],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->configurationFields($form, $this->configuration);

    return $form;
  }

  /**
   * Helper function to implode nested arrays.
   *
   * @param string $separator
   *   String to add between array elements.
   * @param array $array
   *   The array of elements to implode.
   *
   * @return string
   *   A string representation of the imploded, nested array.
   */
  public function implodeRecursive(string $separator, array $array) {
    foreach ($array as $index => $value) {
      if (is_array($value)) {
        $array[$index] = $this->implodeRecursive($separator, $value);
      }
    }
    return implode($separator, $array);
  }

}
