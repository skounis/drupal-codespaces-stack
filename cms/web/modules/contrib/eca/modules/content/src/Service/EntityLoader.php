<?php

namespace Drupal\eca_content\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\eca\EntityOriginalTrait;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\eca\Token\TokenInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Service for loading content entities from ECA plugins.
 */
class EntityLoader {

  use EntityOriginalTrait;
  use PluginFormTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Constructs a new EntityLoader object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\eca\Token\TokenInterface $token_service
   *   The Token services.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TokenInterface $token_service, TranslationInterface $string_translation, YamlParser $yaml_parser, LoggerChannelInterface $logger, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
    $this->stringTranslation = $string_translation;
    $this->yamlParser = $yaml_parser;
    $this->logger = $logger;
    $this->languageManager = $language_manager;
  }

  /**
   * Provides default configuration values for plugins.
   *
   * @return array
   *   The default configuration values.
   */
  public function defaultConfiguration(): array {
    return [
      'from' => 'current',
      'entity_type' => '_none',
      'entity_id' => '',
      'revision_id' => '',
      'properties' => '',
      'langcode' => '_interface',
      'latest_revision' => FALSE,
      'unchanged' => FALSE,
    ];
  }

  /**
   * Builds up the configuration form elements for the given plugin config.
   *
   * @param array $plugin_configuration
   *   The plugin configuration.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form containing the elements.
   */
  public function buildConfigurationForm(array $plugin_configuration, array $form, FormStateInterface $form_state): array {
    $form['from'] = [
      '#type' => 'select',
      '#title' => $this->t('Load entity from'),
      '#options' => $this->getOptions('from'),
      '#default_value' => $plugin_configuration['from'],
      '#description' => $this->t('Loads the entity from the given list and/or properties below.'),
      '#weight' => -70,
      '#eca_token_select_option' => TRUE,
    ];
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getOptions('entity_type'),
      '#default_value' => $plugin_configuration['entity_type'],
      '#description' => $this->t('The type of the entity to be loaded.'),
      '#weight' => -60,
      '#eca_token_select_option' => TRUE,
    ];
    $form['entity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity ID'),
      '#default_value' => $plugin_configuration['entity_id'],
      '#description' => $this->t('The ID of the entity to be loaded.'),
      '#weight' => -50,
    ];
    $form['revision_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Revision ID'),
      '#default_value' => $plugin_configuration['revision_id'],
      '#description' => $this->t('The revision ID of the entity to be loaded.'),
      '#weight' => -40,
    ];
    $form['properties'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Property values'),
      '#default_value' => $plugin_configuration['properties'],
      '#description' => $this->t('A key-value list of raw field values of the entity to load. This will only be used when loading by properties is selected above. Supports YAML format. Example:<em><br/>field_mynumber: 1</em>. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $this->getOptions('langcode'),
      '#default_value' => $plugin_configuration['langcode'],
      '#description' => $this->t('The language code of the entity to be loaded.'),
      '#weight' => -30,
      '#eca_token_select_option' => TRUE,
    ];
    $form['latest_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load latest revision'),
      '#default_value' => $plugin_configuration['latest_revision'],
      '#description' => $this->t('Whether loading the latest revision or not.'),
      '#weight' => -20,
    ];
    $form['unchanged'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load unchanged values'),
      '#default_value' => $plugin_configuration['unchanged'],
      '#description' => $this->t('Whether loading the unchanged values of fields or not.'),
      '#weight' => -10,
    ];
    return $form;
  }

  /**
   * Form validation handler.
   *
   * @param array &$plugin_configuration
   *   The plugin configuration where to put in the submitted form values.
   * @param array &$form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$plugin_configuration, array &$form, FormStateInterface $form_state): void {}

  /**
   * Form submission handler.
   *
   * @param array &$plugin_configuration
   *   The plugin configuration where to put in the submitted form values.
   * @param array &$form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$plugin_configuration, array &$form, FormStateInterface $form_state): void {
    $plugin_configuration['from'] = $form_state->getValue('from');
    $plugin_configuration['entity_type'] = $form_state->getValue('entity_type');
    $plugin_configuration['entity_id'] = $form_state->getValue('entity_id');
    $plugin_configuration['revision_id'] = $form_state->getValue('revision_id');
    $plugin_configuration['properties'] = $form_state->getValue('properties');
    $plugin_configuration['langcode'] = $form_state->getValue('langcode');
    $plugin_configuration['latest_revision'] = !empty($form_state->getValue('latest_revision'));
    $plugin_configuration['unchanged'] = !empty($form_state->getValue('unchanged'));
  }

  /**
   * Get configuration options.
   *
   * @param string $id
   *   The ID for which to provide options.
   *
   * @return array|null
   *   The options, or NULL if it does not provide options for the given ID.
   */
  protected function getOptions(string $id): ?array {
    if ($id === 'from') {
      return [
        'current' => $this->t('Current scope'),
        'id' => $this->t('Type and ID (see below)'),
        'properties' => $this->t('Type and properties (see below)'),
      ];
    }
    if ($id === 'entity_type') {
      $entity_types = [];
      foreach ($this->entityTypeManager->getDefinitions() as $type_definition) {
        if ($type_definition->entityClassImplements(ContentEntityInterface::class)) {
          $entity_types[$type_definition->id()] = $type_definition->getLabel();
        }
      }
      return ['_none' => $this->t('- None chosen -')] + $entity_types;
    }
    if ($id === 'langcode') {
      $langcodes = [];
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        $langcodes[$langcode] = $language->getName();
      }
      return [
        '_interface' => $this->t('Interface language'),
      ] + $langcodes;
    }
    return NULL;
  }

  /**
   * Loads the entity by using the currently given plugin configuration.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   (Optional) A passed through entity object.
   * @param array $plugin_configuration
   *   (Optional) The plugin configuration values.
   * @param string $pluginId
   *   (Optional) The plugin ID which is calling the method.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded entity, or NULL if not found.
   */
  public function loadEntity(?EntityInterface $entity = NULL, array $plugin_configuration = [], string $pluginId = 'eca_token_load_entity'): ?EntityInterface {
    $this->pluginId = $pluginId;
    $config = $plugin_configuration + $this->defaultConfiguration();
    $token = $this->tokenService;
    $from = $config['from'];
    if ($from === '_eca_token') {
      $from = $this->getTokenValue('from', 'current');
    }
    $entity_type = $config['entity_type'];
    if ($entity_type === '_eca_token') {
      $entity_type = $this->getTokenValue('entity_type', '_none');
    }

    switch ($from) {

      case 'id':
        $entity = NULL;
        if (!empty($entity_type)
          && $entity_type !== '_none'
          && $config['entity_id'] !== ''
          && $this->entityTypeManager->hasDefinition($entity_type)) {
          $entity_id = trim((string) $token->replaceClear($config['entity_id']));
          if ($entity_id !== '') {
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
          }
        }
        break;

      case 'properties':
        $entity = NULL;
        $properties = NULL;
        if (!empty($entity_type)
          && $entity_type !== '_none'
          && $config['properties'] !== ''
          && $this->entityTypeManager->hasDefinition($entity_type)) {
          try {
            $properties = $this->yamlParser->parse($config['properties']);
          }
          catch (ParseException $e) {
            $this->logger->error('Tried parsing properties as YAML format for loading an entity, but parsing failed.');
          }
          if (is_array($properties) && !empty($properties)) {
            $storage = $this->entityTypeManager->getStorage($entity_type);
            $query = $storage->getQuery();
            $query->accessCheck(FALSE);
            foreach ($properties as $name => $value) {
              // Cast scalars to array so we can consistently use an IN
              // condition.
              // @see \Drupal\Core\Entity\EntityStorageBase::buildPropertyQuery
              $query->condition($name, (array) $value, 'IN');
            }
            // Make sure to load at most one record.
            $query->range(0, 1);

            $result = $query->execute();
            /** @var int[] $result */
            array_walk($result, static function (&$item) {
              $item = (int) $item;
            });
            /**
             * @var \Drupal\Core\Entity\EntityInterface[] $entities
             */
            $entities = $result ? $storage->loadMultiple($result) : [];
            if ($first = reset($entities)) {
              $entity = $first;
            }
          }
        }
        break;

    }

    if ($entity !== NULL && $config['unchanged']) {
      $original = $this->getOriginal($entity);
      if (!isset($original) && !$entity->isNew()) {
        /**
         * @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage
         */
        $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
        $original = $storage->loadUnchanged($entity->id());
      }
      $entity = $original;
    }

    if ($entity instanceof TranslatableInterface) {
      $langcode = $config['langcode'];
      if ($langcode === '_interface') {
        $langcode = $this->languageManager->getCurrentLanguage()->getId();
      }
      elseif ($langcode === '_eca_token') {
        $langcode = $this->getTokenValue('langcode', $this->languageManager->getCurrentLanguage()->getId());
      }
      if (!(($langcode === LanguageInterface::LANGCODE_DEFAULT) && $entity->isDefaultTranslation()) && ($entity->language()->getId() !== $langcode)) {
        if ($entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($langcode);
        }
        elseif ($config['langcode'] !== '_interface') {
          $entity = NULL;
        }
      }
    }

    if ($entity instanceof RevisionableInterface) {
      /**
       * @var \Drupal\Core\Entity\RevisionableStorageInterface $storage
       */
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      if ($config['latest_revision'] && !$entity->isLatestRevision()) {
        $entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
      }
      elseif ($config['revision_id'] !== '') {
        $entity = NULL;
        $revision_id = trim((string) $token->replaceClear($config['revision_id']));
        if ($revision_id !== '') {
          $entity = $storage->loadRevision($revision_id);
        }
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

}
