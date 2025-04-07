<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Form\Handler\BundleEntityFormHandler;
use Drupal\simple_sitemap\Form\Handler\EntityFormHandler;
use Drupal\simple_sitemap\Form\Handler\EntityFormHandlerInterface;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Settings;

/**
 * Helper class for working with forms.
 */
class FormHelper {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  protected const PRIORITY_HIGHEST = 10;
  protected const PRIORITY_DIVIDER = 10;

  protected const ENTITY_FORM_HANDLER = EntityFormHandler::class;
  protected const BUNDLE_ENTITY_FORM_HANDLER = BundleEntityFormHandler::class;

  /**
   * The sitemap generator service.
   *
   * @var \Drupal\simple_sitemap\Manager\Generator
   */
  protected $generator;

  /**
   * Helper class for working with entities.
   *
   * @var \Drupal\simple_sitemap\Entity\EntityHelper
   */
  protected $entityHelper;

  /**
   * Proxy for the current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The simple_sitemap.settings service.
   *
   * @var \Drupal\simple_sitemap\Settings
   */
  protected $settings;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Cron intervals.
   *
   * @var int[]
   */
  protected static $cronIntervals = [1, 3, 6, 12, 24, 48, 72, 96, 120, 144, 168];

  /**
   * FormHelper constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Proxy for the current user account.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(
    Generator $generator,
    Settings $settings,
    EntityHelper $entity_helper,
    AccountProxyInterface $current_user,
    ClassResolverInterface $class_resolver,
  ) {
    $this->generator = $generator;
    $this->settings = $settings;
    $this->entityHelper = $entity_helper;
    $this->currentUser = $current_user;
    $this->classResolver = $class_resolver;
  }

  /**
   * Alters the specified form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see simple_sitemap_form_alter()
   * @see simple_sitemap_engines_form_alter()
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    if (!$this->formAlterAccess()) {
      return;
    }

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }

    $form_handler = $this->resolveEntityFormHandler($form_object->getEntity());

    if ($form_handler instanceof EntityFormHandlerInterface && $form_handler->isSupportedOperation($form_object->getOperation())) {
      $entity_type_id = $form_handler->getEntityTypeId();

      if ($this->generator->entityManager()->entityTypeIsEnabled($entity_type_id)) {
        $form_handler->formAlter($form, $form_state);
      }
    }
  }

  /**
   * Determines whether a form can be altered.
   *
   * @return bool
   *   TRUE if a form can be altered, FALSE otherwise.
   */
  protected function formAlterAccess(): bool {
    return $this->currentUser->hasPermission('administer sitemap settings')
      || $this->currentUser->hasPermission('edit entity sitemap settings');
  }

  /**
   * Resolves the entity form handler for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the form handler should be resolved.
   *
   * @return \Drupal\simple_sitemap\Form\Handler\EntityFormHandlerInterface|null
   *   The instance of the entity form handler or NULL if there is no handler
   *   for the given entity.
   */
  public function resolveEntityFormHandler(EntityInterface $entity): ?EntityFormHandlerInterface {
    $definition = $this->resolveEntityFormHandlerDefinition($entity);

    if ($definition) {
      return $this->classResolver
        ->getInstanceFromDefinition($definition)
        ->setEntity($entity);
    }
    return NULL;
  }

  /**
   * Resolves the definition of the entity form handler for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the definition should be resolved.
   *
   * @return string|null
   *   A string with definition of the entity form handler or NULL if there is
   *   no definition for the given entity.
   */
  protected function resolveEntityFormHandlerDefinition(EntityInterface $entity): ?string {
    $entity_type_id = $entity->getEntityTypeId();

    if ($this->entityHelper->supports($entity->getEntityType())) {
      return static::ENTITY_FORM_HANDLER;
    }
    // Menu fix.
    elseif ($entity_type_id === 'menu') {
      return static::BUNDLE_ENTITY_FORM_HANDLER;
    }
    else {
      foreach ($this->entityHelper->getSupportedEntityTypes() as $entity_type) {
        if ($entity_type->getBundleEntityType() === $entity_type_id) {
          return static::BUNDLE_ENTITY_FORM_HANDLER;
        }
      }
    }
    return NULL;
  }

  /**
   * Returns a form to configure the bundle settings.
   *
   * @param array $form
   *   The form where the bundle settings form is being included in.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_name
   *   The bundle name.
   *
   * @return array
   *   The form elements for the bundle settings.
   */
  public function bundleSettingsForm(array $form, $entity_type_id, $bundle_name): array {
    /** @var \Drupal\simple_sitemap\Form\Handler\BundleEntityFormHandler $form_handler */
    $form_handler = $this->classResolver->getInstanceFromDefinition(static::BUNDLE_ENTITY_FORM_HANDLER);

    return $form_handler->setEntityTypeId($entity_type_id)
      ->setBundleName($bundle_name)
      ->settingsForm($form);
  }

  /**
   * Adds the 'regenerate all sitemaps' checkbox to the form.
   *
   * @param array $form
   *   The form where the checkbox is being included in.
   *
   * @return array
   *   The form elements with checkbox.
   */
  public function regenerateNowForm(array $form): array {
    $form['simple_sitemap_regenerate_now'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate all sitemaps after hitting <em>Save</em>'),
      '#description' => $this->t('This setting will regenerate all sitemaps including the above changes.'),
      '#access' => $this->currentUser->hasPermission('administer sitemap settings'),
      '#default_value' => FALSE,
      '#tree' => FALSE,
      '#weight' => 90,
    ];

    if ($this->settings->get('cron_generate')) {
      $form['simple_sitemap_regenerate_now']['#description'] .= '<br>' . $this->t('Otherwise the sitemaps will be regenerated during a future cron run.');
    }

    return $form;
  }

  /**
   * Form submission handler.
   *
   * Regenerates sitemaps according to user setting.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function regenerateNowFormSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $this->generator->setSitemaps()->rebuildQueue()->generate();
    }
  }

  /**
   * Returns a form to configure the sitemap settings.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param array $settings
   *   The sitemap settings.
   *
   * @return array
   *   The form elements for the sitemap settings.
   */
  public function settingsForm(array $form, array $settings): array {
    $form['#after_build'][] = [static::class, 'settingsFormStates'];

    $form['index'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index'),
      '#default_value' => (int) ($settings['index'] ?? 0),
    ];
    $form['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#default_value' => $settings['priority'] ?? NULL,
      '#options' => static::getPriorityOptions(),
    ];
    $form['changefreq'] = [
      '#type' => 'select',
      '#title' => $this->t('Change frequency'),
      '#default_value' => $settings['changefreq'] ?? NULL,
      '#options' => static::getChangefreqOptions(),
      '#empty_option' => $this->t('- Not specified -'),
    ];
    $form['include_images'] = [
      '#type' => 'select',
      '#title' => $this->t('Include images'),
      '#default_value' => (int) ($settings['include_images'] ?? 0),
      '#options' => [$this->t('No'), $this->t('Yes')],
    ];

    return $form;
  }

  /**
   * After-build callback to set the correct #states.
   *
   * @param array $element
   *   The element structure.
   *
   * @return array
   *   The element structure.
   */
  public static function settingsFormStates(array $element): array {
    $conditions = $element['index']['#type'] === 'checkbox' ? ['checked' => TRUE] : ['value' => 1];
    $selector = ':input[name="' . $element['index']['#name'] . '"]';

    foreach (Element::children($element) as $key) {
      if ($key !== 'index') {
        $element[$key]['#states']['visible'][$selector] = $conditions;
      }
    }

    return $element;
  }

  /**
   * Gets the options for the priority dropdown setting.
   *
   * @return array
   *   The options for the priority dropdown setting.
   */
  public static function getPriorityOptions(): array {
    $options = [];

    foreach (range(0, static::PRIORITY_HIGHEST) as $value) {
      $value = static::formatPriority($value / static::PRIORITY_DIVIDER);
      $options[$value] = $value;
    }

    return $options;
  }

  /**
   * Gets the options for the changefreq dropdown setting.
   *
   * @return array
   *   The options for the changefreq dropdown setting.
   */
  public static function getChangefreqOptions(): array {
    return [
      'always' => t('always'),
      'hourly' => t('hourly'),
      'daily' => t('daily'),
      'weekly' => t('weekly'),
      'monthly' => t('monthly'),
      'yearly' => t('yearly'),
      'never' => t('never'),
    ];
  }

  /**
   * Formats the given priority.
   *
   * @param string $priority
   *   The priority to format.
   *
   * @return string
   *   The formatted priority.
   */
  public static function formatPriority(string $priority): string {
    return number_format((float) $priority, 1, '.', '');
  }

  /**
   * Validates the priority.
   *
   * @param string $priority
   *   The priority value.
   *
   * @return bool
   *   TRUE if the priority is valid.
   */
  public static function isValidPriority(string $priority): bool {
    return is_numeric($priority) && $priority >= 0 && $priority <= 1;
  }

  /**
   * Validates the change frequency.
   *
   * @param string $changefreq
   *   The change frequency value.
   *
   * @return bool
   *   TRUE if the change frequency is valid.
   */
  public static function isValidChangefreq(string $changefreq): bool {
    return array_key_exists($changefreq, static::getChangefreqOptions());
  }

  /**
   * Gets the cron intervals.
   *
   * @return array
   *   Cron intervals.
   */
  public static function getCronIntervalOptions(): array {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $formatter */
    $formatter = \Drupal::service('date.formatter');
    $intervals = array_flip(static::$cronIntervals);
    foreach ($intervals as $interval => &$label) {
      $label = $formatter->formatInterval($interval * 60 * 60);
    }

    return [0 => t('On every cron run')] + $intervals;
  }

}
