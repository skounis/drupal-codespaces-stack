<?php

namespace Drupal\simple_sitemap_engines\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine;

/**
 * Form for managing search engine submission settings.
 */
class SimplesitemapEnginesForm extends ConfigFormBase {

  use AutowireTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * SimplesitemapEnginesForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    DateFormatterInterface $date_formatter,
    StateInterface $state,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->dateFormatter = $date_formatter;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_sitemap_engines_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap_engines.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('simple_sitemap_engines.settings');

    $form['#tree'] = TRUE;

    $form['index_now'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IndexNow settings'),
    ];

    $form['index_now']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Submit changes to IndexNow capable engines'),
      '#description' => $this->t('Send change notice to IndexNow compatible search engines right after submitting entity forms. Changes include creating, deleting and updating of an entity.<br/>This behavior can be overridden on entity forms. Don\'t forget to <a href="@inclusion_url">include entities</a>.',
        ['@inclusion_url' => Url::fromRoute('simple_sitemap.entities')->toString()]
      ),
      '#default_value' => $config->get('index_now_enabled'),
    ];

    $form['index_now']['preferred_engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred IndexNow engine'),
      '#description' => $this->t('All IndexNow requests will be sent to the engine selected here. Only one engine needs to be notified, as it will notify other IndexNow compatible engines for you.<br/>For the sake of equality of opportunity, <strong>consider leaving this at <em>Random</em></strong>, so a random engine can be picked on each submission.'),
      '#default_value' => $config->get('index_now_preferred_engine'),
      '#options' => ['' => '- ' . $this->t('Random') . ' -'] + array_map(function ($engine) {
        return $engine->label();
      }, SimpleSitemapEngine::loadIndexNowEngines()),
      '#states' => [
        'visible' => [':input[name="index_now[enabled]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['index_now']['on_entity_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Index on every entity save operation'),
      '#description' => $this->t('If checked, all entity save operations for <a href="@inclusion_url">included entities</a> will trigger notification of IndexNow search engines.<br/>If unchecked, this will only be possible by adding/altering/deleting an entity through a form.<br/>This should be unchecked if there are mass operations performed on entities that are irrelevant to indexing.',
        ['@inclusion_url' => Url::fromRoute('simple_sitemap.entities')->toString()]
      ),
      '#default_value' => $config->get('index_now_on_entity_save'),
      '#states' => [
        'visible' => [':input[name="index_now[enabled]"]' => ['checked' => TRUE]],
      ],
    ];

    $key_location = self::getKeyLocation();
    switch ($key_location) {
      case 'settings':
        $text = self::getKeyStatusMessage('settings_info');
        break;

      case 'settings_state':
        $text = self::getKeyStatusMessage('settings_info');
        $this->messenger()->addWarning(self::getKeyStatusMessage('settings_state_warning'));
        break;

      case 'state':
        $text = self::getKeyStatusMessage('state_info');
        $this->messenger()->addWarning(self::getKeyStatusMessage('state_warning'));
        break;

      default:
        $text = self::getKeyStatusMessage('missing_warning');
        $this->messenger()->addWarning($text);
    }

    $form['index_now']['key'] = [
      '#type' => 'submit',
      '#value' => in_array($key_location, ['state', 'settings_state'])
        ? $this->t('Remove verification key from state')
        : $this->t('Generate verification key'),
      '#submit' => in_array($key_location, ['state', 'settings_state'])
        ? [self::class . '::removeKey']
        : [self::class . '::generateKey'],
      '#disabled' => $key_location === 'settings',
      '#validate' => [],
      '#prefix' => '<p>' . $text . '</p>',
    ];

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sitemap submission settings'),
    ];

    $form['settings']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Submit sitemaps to search engines'),
      '#description' => $this->t("This enables/disables sitemap submission; don't forget to choose sitemaps below.<br/>The ping protocol is <strong>being deprecated</strong>, use IndexNow if applicable."),
      '#default_value' => $config->get('enabled'),
    ];

    $form['settings']['submission_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Submission interval'),
      '#options' => FormHelper::getCronIntervalOptions(),
      '#default_value' => $config->get('submission_interval'),
      '#states' => [
        'visible' => [':input[name="settings[enabled]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['settings']['engines'] = [
      '#type' => 'details',
      '#title' => $this->t('Engines'),
      '#markup' => '<div class="description">'
      . $this->t('Choose which sitemaps are to be submitted to which search engines.<br>Sitemaps can be configured <a href="@url">here</a>.',
          ['@url' => Url::fromRoute('entity.simple_sitemap.collection')->toString()]
      )
      . '</div>',
      '#open' => TRUE,
      '#states' => [
        'visible' => [':input[name="settings[enabled]"]' => ['checked' => TRUE]],
      ],
    ];

    $sitemaps = SimpleSitemap::loadMultiple();
    foreach (SimpleSitemapEngine::loadSitemapSubmissionEngines() as $engine_id => $engine) {
      $form['settings']['engines'][$engine_id] = [
        '#type' => 'select',
        '#title' => $engine->label(),
        '#options' => array_map(
          function ($sitemap) {
            return $sitemap->label();
          },
          $sitemaps
        ),
        '#default_value' => $engine->sitemap_variants,
        '#multiple' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Gets the status message of the IndexNow key.
   *
   * @param string $type
   *   The message's type.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The status message.
   */
  public static function getKeyStatusMessage(string $type): TranslatableMarkup {
    $key = \Drupal::service('simple_sitemap.engines.index_now_submitter')->getKey();
    switch ($type) {
      case 'settings_info':
        return t('The IndexNow verification key is saved in <em>settings.php</em>: @key', ['@key' => $key]);

      case 'state_info':
        return t('The IndexNow verification key is defined in <em>Drupal state</em>: @key', ['@key' => $key]);

      case 'state_warning':
        return t('The IndexNow verification key is saved in <em>Drupal state</em>. Consider defining it in <em>settings.php</em> like so:<br/>@code', ['@code' => '$settings[\'simple_sitemap_engines.index_now.key\'] = ' . "'$key';"]);

      case 'settings_state_warning':
        return t('The IndexNow verification key is saved in <em>settings.php</em> and can be safely removed from <em>Drupal state</em>.');

      case 'missing_warning':
      default:
        return t('An IndexNow verification key needs to be generated and optionally added to <em>settings.php</em> in order for IndexNow engines to get notified about changes. This warning only applies to the production environment.');
    }
  }

  /**
   * Gets the location of the IndexNow key.
   *
   * @return string|null
   *   The location of the IndexNow key.
   */
  public static function getKeyLocation(): ?string {
    $settings = (bool) Settings::get('simple_sitemap_engines.index_now.key');
    $state = (bool) \Drupal::state()->get('simple_sitemap_engines.index_now.key');

    if ($settings && $state) {
      return 'settings_state';
    }
    if ($settings) {
      return 'settings';
    }
    if ($state) {
      return 'state';
    }

    return NULL;
  }

  /**
   * Generates a new IndexNow key and saves it to state.
   */
  public static function generateKey(): void {
    \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_WARNING);

    /** @var \Drupal\Component\Uuid\UuidInterface $uuid */
    $uuid = \Drupal::service('uuid');
    \Drupal::state()->set('simple_sitemap_engines.index_now.key', $uuid->generate());
  }

  /**
   * Removes the IndexNow key from state.
   */
  public static function removeKey(): void {
    \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_WARNING);
    \Drupal::state()->delete('simple_sitemap_engines.index_now.key');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach (SimpleSitemapEngine::loadSitemapSubmissionEngines() as $id => $engine) {
      if (!empty($values = $form_state->getValue(['settings', 'engines', $id]))) {
        $submit = TRUE;
      }
      $engine->sitemap_variants = $values;
      $engine->save();
    }

    $config = $this->config('simple_sitemap_engines.settings');

    $enabled = (bool) $form_state->getValue(['settings', 'enabled']);
    $index_now_enabled = (bool) $form_state->getValue(['index_now', 'enabled']);

    // Clear necessary caches to apply field definition updates.
    // @see simple_sitemap_engines_entity_extra_field_info()
    if ($config->get('index_now_enabled') !== $index_now_enabled) {
      $this->entityFieldManager->clearCachedFieldDefinitions();
    }

    $config->set('enabled', $enabled)
      ->set('submission_interval', $form_state->getValue([
        'settings',
        'submission_interval',
      ]))
      ->set('index_now_enabled', $index_now_enabled)
      ->set('index_now_preferred_engine', $form_state->getValue([
        'index_now',
        'preferred_engine',
      ]))
      ->set('index_now_on_entity_save', $form_state->getValue([
        'index_now',
        'on_entity_save',
      ]))
      ->save();

    if ($enabled && empty($submit)) {
      $this->messenger()->addWarning($this->t('No sitemaps have been selected for submission.'));
    }

    parent::submitForm($form, $form_state);
  }

}
