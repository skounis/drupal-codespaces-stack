<?php

namespace Drupal\eca_config\Plugin\ECA\Event;

use Drupal\Core\Config\ConfigCollectionEvents;
use Drupal\Core\Config\ConfigCollectionInfo;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\Importer\MissingContentEvent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the ECA Events for config.
 *
 * @EcaEvent(
 *   id = "config",
 *   deriver = "Drupal\eca_config\Plugin\ECA\Event\ConfigEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ConfigEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'delete' => [
        'label' => 'Delete config',
        'event_name' => ConfigEvents::DELETE,
        'event_class' => ConfigCrudEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'collection_info' => [
        'label' => 'Collect information on all config collections',
        'event_name' => ConfigCollectionEvents::COLLECTION_INFO,
        'event_class' => ConfigCollectionInfo::class,
        'tags' => Tag::READ | Tag::PERSISTENT | Tag::AFTER,
      ],
      'import' => [
        'label' => 'Import config',
        'event_name' => ConfigEvents::IMPORT,
        'event_class' => ConfigImporterEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'import_missing_content' => [
        'label' => 'Import config but content missing',
        'event_name' => ConfigEvents::IMPORT_MISSING_CONTENT,
        'event_class' => MissingContentEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::BEFORE,
      ],
      'import_validate' => [
        'label' => 'Import config validation',
        'event_name' => ConfigEvents::IMPORT_VALIDATE,
        'subscriber_priority' => 1024,
        'event_class' => ConfigImporterEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::BEFORE,
      ],
      'rename' => [
        'label' => 'Rename config',
        'event_name' => ConfigEvents::RENAME,
        'event_class' => ConfigRenameEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::BEFORE,
      ],
      'save' => [
        'label' => 'Save config',
        'event_name' => ConfigEvents::SAVE,
        'event_class' => ConfigCrudEvent::class,
        'tags' => Tag::CONFIG | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $default = parent::defaultConfiguration();
    if ($this->eventClass() === ConfigCrudEvent::class) {
      $default['config_name'] = '';
      $default['sync_mode'] = '';
      if ($this->eventName() === ConfigEvents::SAVE) {
        $default['write_mode'] = '';
      }
    }
    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($this->eventClass() === ConfigCrudEvent::class) {
      $form['config_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Config name'),
        '#description' => $this->t('Leave empty to apply to all events. To limit this event to just specific events, provide the machine name of the configuration, e.g. "system.site". This can also be limited to a range of configuration, e.g. with "node.type.", this applies to all configurations with a name starting with this pattern.'),
        '#default_value' => $this->configuration['config_name'],
      ];
      $form['sync_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Sync Mode'),
        '#description' => $this->t('During config sync, the Drupal site is in sync mode.'),
        '#default_value' => $this->configuration['sync_mode'],
        '#options' => [
          '' => $this->t('always'),
          'yes' => $this->t('Only in sync mode'),
          'no' => $this->t('Only when not sync mode'),
        ],
      ];
      if ($this->eventName() === ConfigEvents::SAVE) {
        $form['write_mode'] = [
          '#type' => 'select',
          '#title' => $this->t('Write Mode'),
          '#default_value' => $this->configuration['write_mode'],
          '#options' => [
            '' => $this->t('always'),
            'new' => $this->t('Only when config is new'),
            'update' => $this->t('Only when config already exists'),
          ],
        ];
      }
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if ($this->eventClass() === ConfigCrudEvent::class) {
      $this->configuration['config_name'] = trim($form_state->getValue('config_name'));
      $this->configuration['sync_mode'] = trim($form_state->getValue('sync_mode'));
      if ($this->eventName() === ConfigEvents::SAVE) {
        $this->configuration['write_mode'] = trim($form_state->getValue('write_mode'));
      }
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEvent $ecaEvent): string {
    if ($this->eventClass() === ConfigCrudEvent::class) {
      $parts = [];
      $configuration = $ecaEvent->getConfiguration();
      $parts[] = empty($configuration['config_name']) ? '*' : $configuration['config_name'];
      $parts[] = empty($configuration['sync_mode']) ? '*' : $configuration['sync_mode'];
      if ($this->eventName() === ConfigEvents::SAVE) {
        $parts[] = empty($configuration['write_mode']) ? '*' : $configuration['write_mode'];
      }
      return implode('::', $parts);
    }
    return parent::generateWildcard($eca_config_id, $ecaEvent);
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    if ($event::class === ConfigCrudEvent::class) {
      $parts = explode('::', $wildcard);
      if ($parts[0] !== '*' && !str_starts_with($event->getConfig()->getName(), $parts[0])) {
        return FALSE;
      }
      $applies = TRUE;
      if ($parts[1] !== '*') {
        $isSyncing = \Drupal::isConfigSyncing();
        $applies = ($parts[1] === 'yes' && $isSyncing) || ($parts[1] === 'no' && !$isSyncing);
      }
      if ($applies && $event_name === ConfigEvents::SAVE && $parts[2] !== '*') {
        $isNew = empty($event->getConfig()->getOriginal());
        $applies = ($parts[2] === 'new' && $isNew) || ($parts[2] === 'update' && !$isNew);
      }
      return $applies;
    }
    return parent::appliesForWildcard($event, $event_name, $wildcard);
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'config',
    description: 'The configuration with all current values.',
  )]
  #[Token(
    name: 'config_original',
    description: 'The configuration with all original values plus overrides.',
  )]
  #[Token(
    name: 'config_name',
    description: 'The name of the configuration.',
  )]
  public function getData(string $key): mixed {
    $event = $this->event;
    if ($event instanceof ConfigCrudEvent) {
      $config = $event->getConfig();
      switch ($key) {
        case 'config':
          return DataTransferObject::create($config->get());

        case 'config_original':
          return DataTransferObject::create($config->getOriginal());

        case 'config_name':
          return $config->getName();
      }
    }
    return parent::getData($key);
  }

}
