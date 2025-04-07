<?php

namespace Drupal\scheduler\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main administration form for the Scheduler module.
 */
class SchedulerAdminForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The scheduler manager service.
   *
   * @var \Drupal\scheduler\SchedulerManager
   */
  protected $schedulerManager;

  /**
   * Entity Type Manager service object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setDateFormatter($container->get('date.formatter'));
    $instance->schedulerManager = $container->get('scheduler.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Sets the date formatter.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  protected function setDateFormatter(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduler_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['scheduler.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Most of the Scheduler options are set independently for each entity type and bundle. These can be accessed from the <a href="@link">admin structure</a> page or directly by using the drop-button', ['@link' => Url::fromRoute('system.admin_structure')->toString()]) . '</p>',
    ];

    // Build a drop-button with links to configure all supported entity types.
    $plugins = $this->schedulerManager->getPlugins();
    $links = [];
    $links[] = [
      'title' => $this->t('Entity Types'),
      'url' => Url::fromRoute('system.admin_structure'),
    ];
    foreach ($plugins as $entityTypeId => $plugin) {
      $publishing_enabled_types = $this->schedulerManager->getEnabledTypes($entityTypeId, 'publish');
      $unpublishing_enabled_types = $this->schedulerManager->getEnabledTypes($entityTypeId, 'unpublish');

      // When a module is enabled via drush there is no automatic clear cache.
      // Thus moduleHandler()->moduleExists({module}) can return false when
      // the module is actually enabled. This means we get nothing for
      // plugin->getTypes() and processing should stop with a useful exception
      // message, instead of letting Core give a confusing exception later.
      $bundle_id = $this->entityTypeManager->getDefinition($entityTypeId)->getBundleEntityType();
      $entity_type_definition = $this->entityTypeManager->getDefinition($bundle_id, FALSE);
      if (!$entity_type_definition) {
        throw new \Exception(sprintf('Invalid or empty %s entity type definition for %s module. Do a full cache clear via admin/config/development/performance or drush cr.', $bundle_id, $plugin->dependency()));
      }
      $collection_label = (string) ($entity_type_definition->get('label_collection') ?: $entity_type_definition->get('label'));

      // $plugin->getTypes() will usually give a non-empty array of values, but
      // it can be empty if no default bundle type is defined, or all types have
      // been deleted.
      if (!$types = $plugin->getTypes()) {
        // Some modules may not create a default entity type during installation
        // or the entity type definitions may have been deleted. This is not an
        // exception, but will cause an error if we do not stop this loop.
        $message_params = [
          '%module' => $plugin->dependency(),
          '%plugin_label' => $plugin->label(),
          '%bundle_id' => $bundle_id,
        ];
        $this->logger('scheduler')->notice('No %bundle_id entity types returned by %module module for use in %plugin_label', $message_params);
        $links[] = ['title' => "-- $collection_label --  (" . $this->t('no entity types defined') . ')'];
        continue;
      }

      $links[] = ['title' => "-- $collection_label --"];
      foreach ($types as $id => $type) {
        $text = [];
        in_array($id, $publishing_enabled_types) ? $text[] = $this->t('publishing') : NULL;
        in_array($id, $unpublishing_enabled_types) ? $text[] = $this->t('unpublishing') : NULL;
        $links[] = [
          'title' => $type->label() . (!empty($text) ? ' (' . implode(', ', $text) . ')' : ''),
          // Example: the route 'entity.media_type.edit_form' with parameter
          // media_type={typeid} has url /admin/structure/media/manage/{typeid}.
          'url' => Url::fromRoute("entity.$bundle_id.edit_form", [$bundle_id => $type->id()]),
        ];
      }
    }
    $form['entity_type_links'] = [
      '#type' => 'dropbutton',
      '#links' => $links,
    ];

    $form['description2'] = [
      '#markup' => '<p>' . $this->t('The settings below are common to all entity types.') . '</p>',
    ];

    // Options for setting date-only with default time.
    $form['date_only_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date only'),
      '#collapsible' => FALSE,
    ];
    $form['date_only_fieldset']['allow_date_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to enter only a date and provide a default time.'),
      '#default_value' => $this->setting('allow_date_only'),
      '#description' => $this->t('When only a date is entered the time will default to a specified value, but the user can change this if required.'),
    ];
    $form['date_only_fieldset']['default_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default time'),
      '#default_value' => $this->setting('default_time'),
      '#size' => 20,
      '#maxlength' => 8,
      '#description' => $this->t('Provide a default time in @format format that will be used if the user does not enter a value.', ['@format' => $this->setting('hide_seconds') ? 'HH:MM' : 'HH:MM:SS']),
      '#states' => [
        'visible' => [
          ':input[name="allow_date_only"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Options for configuring the time input field.
    $form['time_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Time settings'),
      '#collapsible' => FALSE,
    ];
    $form['time_fieldset']['hide_seconds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the seconds.'),
      '#default_value' => $this->setting('hide_seconds'),
      '#description' => $this->t('When entering a time, only show hours and minutes in the input field.'),
    ];

    // Attach library for admin css file.
    $form['#attached']['library'][] = 'scheduler/admin-css';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $hide_seconds = $form_state->getValue(['hide_seconds']);
    // If date-only is enabled then check if a valid default time was entered.
    // Leading zeros and seconds can be omitted, eg. 6:30 is considered valid.
    if ($form_state->getValue(['allow_date_only'])) {
      $default_time = date_parse($form_state->getValue(['default_time']));
      if ($default_time['error_count'] || strlen($form_state->getValue(['default_time'])) < 3) {
        $form_state->setErrorByName('default_time', $this->t('The default time should be in the format @format', ['@format' => $hide_seconds ? 'HH:MM' : 'HH:MM:SS']));
      }
      else {
        // Insert any possibly omitted leading zeroes. If hiding the seconds
        // then ignore any entered seconds and save in H:i format.
        $unix_time = mktime($default_time['hour'], $default_time['minute'], $hide_seconds ? 0 : $default_time['second']);
        $form_state->setValue(['default_time'], $this->dateFormatter->format($unix_time, 'custom', $hide_seconds ? 'H:i' : 'H:i:s'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('scheduler.settings')
      ->set('allow_date_only', $form_state->getValue(['allow_date_only']))
      ->set('default_time', $form_state->getValue('default_time'))
      ->set('hide_seconds', $form_state->getValue('hide_seconds'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Helper method to access the settings of this module.
   *
   * @param string $key
   *   The key of the configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The value of the config setting requested.
   */
  protected function setting($key) {
    return $this->configFactory->get('scheduler.settings')->get($key);
  }

}
