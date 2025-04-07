<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Base action to access the various key value stores.
 */
abstract class KeyValueStoreBase extends ConfigurableActionBase {

  /**
   * The key value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueStoreFactory;

  /**
   * The expirable key value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $expirableKeyValueStoreFactory;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempStoreFactory;

  /**
   * The shared temp store store factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected SharedTempStoreFactory $sharedTempStoreFactory;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->keyValueStoreFactory = $container->get('keyvalue');
    $instance->expirableKeyValueStoreFactory = $container->get('keyvalue.expirable');
    $instance->privateTempStoreFactory = $container->get('tempstore.private');
    $instance->sharedTempStoreFactory = $container->get('tempstore.shared');
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * Return the store for the given store factory.
   *
   * @param string $collection
   *   The collection inside the store.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface|\Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface|\Drupal\Core\TempStore\PrivateTempStore|\Drupal\Core\TempStore\SharedTempStore
   *   The store.
   */
  abstract protected function store(string $collection): KeyValueStoreExpirableInterface|KeyValueStoreInterface|SharedTempStore|PrivateTempStore;

  /**
   * Return TRUE, if the plugin is for writing to the store, FALSE for reading.
   *
   * @return bool
   *   TRUE for writing, FALSE for reading.
   */
  protected function writeMode(): bool {
    return FALSE;
  }

  /**
   * Return TRUE, if the plugin is for deleting from the store, FALSE otherwise.
   *
   * @return bool
   *   TRUE for deleting, FALSE otherwise.
   */
  protected function deleteMode(): bool {
    return FALSE;
  }

  /**
   * Return TRUE, if the store supports to store only if key does not exist yet.
   *
   * @return bool
   *   TRUE, if supported, FALSE otherwise.
   */
  protected function supportsIfNotExists(): bool {
    return TRUE;
  }

  /**
   * Does the actual storage.
   *
   * @param string $collection
   *   The collection.
   * @param bool $ifNotExists
   *   Whether to only store if key does not yet exist.
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value to store.
   *
   * @throws \Exception
   */
  protected function doStore(string $collection, bool $ifNotExists, string $key, mixed $value): void {
    if ($ifNotExists) {
      $this->store($collection)->setIfNotExists($key, $value);
    }
    else {
      $this->store($collection)->set($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $collection = $this->tokenService->replaceClear($this->configuration['collection']);
    $key = $this->tokenService->replaceClear($this->configuration['key']);
    $result = AccessResult::allowedIf(is_string($collection) && $collection !== '' && is_string($key) && $key !== '');
    if (!$result->isAllowed()) {
      $result->setReason('The given collection and/or key is invalid.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function execute(): void {
    $collection = $this->tokenService->replaceClear($this->configuration['collection']);
    $key = $this->tokenService->replaceClear($this->configuration['key']);
    if ($this->writeMode()) {
      $value = $this->configuration['value'];
      if ($this->configuration['use_yaml']) {
        try {
          $value = $this->yamlParser->parse($value);
        }
        catch (ParseException) {
          $this->logger->error('Tried parsing a value as YAML format, but parsing failed.');
          return;
        }
      }
      else {
        // Allow direct assignment of available data from the Token environment.
        $value = $this->tokenService->getOrReplace($value);
      }

      $ifNotExists = $this->supportsIfNotExists() ?
        $this->configuration['ifnotexists'] :
        FALSE;
      $this->doStore($collection, $ifNotExists, $key, $value);
    }
    elseif ($this->deleteMode()) {
      $this->store($collection)->delete($key);
    }
    else {
      $value = $this->store($collection)->get($key);
      $this->tokenService->addTokenData($this->configuration['token_name'], $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $values = [
      'collection' => '',
      'key' => '',
    ];
    if ($this->writeMode()) {
      $values['value'] = '';
      $values['use_yaml'] = FALSE;
      if ($this->supportsIfNotExists()) {
        $values['ifnotexists'] = FALSE;
      }
    }
    elseif (!$this->deleteMode()) {
      $values['token_name'] = '';
    }
    return $values + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Collection'),
      '#default_value' => $this->configuration['collection'],
      '#weight' => -90,
      '#description' => $this->t('The collection of the store.'),
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Store key'),
      '#default_value' => $this->configuration['key'],
      '#weight' => -80,
      '#description' => $this->t('The key of the value in the store.'),
    ];
    if ($this->writeMode()) {
      $form['value'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Value'),
        '#default_value' => $this->configuration['value'],
        '#weight' => -70,
        '#description' => $this->t('The value to store.'),
      ];
      $form['use_yaml'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Interpret above value as YAML format'),
        '#description' => $this->t('Nested data can be set using YAML format, for example <em>mykey: "My value"</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
        '#default_value' => $this->configuration['use_yaml'],
        '#weight' => -65,
      ];
      if ($this->supportsIfNotExists()) {
        $form['ifnotexists'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Only if new'),
          '#default_value' => $this->configuration['ifnotexists'],
          '#weight' => -60,
          '#description' => $this->t('If enabled, this only stores the value if the key does not exist yet.'),
        ];
      }
    }
    elseif (!$this->deleteMode()) {
      $form['token_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name of token'),
        '#default_value' => $this->configuration['token_name'],
        '#weight' => -10,
        '#description' => $this->t('The name of the token, the value is stored into.'),
        '#eca_token_reference' => TRUE,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['collection'] = $form_state->getValue('collection');
    $this->configuration['key'] = $form_state->getValue('key');
    if ($this->writeMode()) {
      $this->configuration['value'] = $form_state->getValue('value');
      $this->configuration['use_yaml'] = $form_state->getValue('use_yaml');
      if ($this->supportsIfNotExists()) {
        $this->configuration['ifnotexists'] = $form_state->getValue('ifnotexists');
      }
    }
    elseif (!$this->deleteMode()) {
      $this->configuration['token_name'] = $form_state->getValue('token_name');
    }
    parent::submitConfigurationForm($form, $form_state);
  }

}
