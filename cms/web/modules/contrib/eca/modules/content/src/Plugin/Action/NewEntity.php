<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a new content entity without saving it.
 *
 * @Action(
 *   id = "eca_new_entity",
 *   label = @Translation("Entity: create new"),
 *   description = @Translation("Create a new content entity without saving it."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class NewEntity extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * The instantiated entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypes = $container->get('eca.service.content_entity_types');
    $plugin->languageManager = $container->get('language_manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'type' => '',
      'langcode' => '',
      'label' => '',
      'published' => FALSE,
      'owner' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('Provide the name of a token that holds the new entity.'),
      '#weight' => -60,
      '#eca_token_reference' => TRUE,
    ];
    $options = $this->entityTypes->getTypesAndBundles(FALSE, FALSE);
    unset($options['user user']);
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $options,
      '#default_value' => $this->configuration['type'],
      '#description' => $this->t('The type of the new entity.<br/>Note: to create a new user entity, enable the eca_user module and use the "User: create new" action from there.'),
      '#weight' => -50,
      '#eca_token_select_option' => TRUE,
    ];
    $langcodes = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $langcodes[$langcode] = $language->getName();
    }
    $langcodes['_interface'] = $this->t('Interface language');
    $form['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $langcodes,
      '#default_value' => $this->configuration['langcode'],
      '#description' => $this->t('The language code of the new entity.'),
      '#weight' => -40,
      '#eca_token_select_option' => TRUE,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity label'),
      '#default_value' => $this->configuration['label'],
      '#description' => $this->t('The label of the new entity.'),
      '#weight' => -30,
    ];
    $form['published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Published'),
      '#default_value' => $this->shouldPublish(),
      '#description' => $this->t('Whether the entity should be published or not.'),
      '#weight' => -20,
    ];
    $form['owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner UID'),
      '#default_value' => $this->configuration['owner'],
      '#description' => $this->t('The owner UID of the new entity.'),
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['type'] = $form_state->getValue('type');
    $this->configuration['langcode'] = $form_state->getValue('langcode');
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['published'] = !empty($form_state->getValue('published'));
    $this->configuration['owner'] = $form_state->getValue('owner');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Access\AccessResultInterface $access_result */
    $access_result = parent::access($object, $account, TRUE);
    if ($this->configuration['type'] === '_eca_token') {
      $type = $this->getTokenValue('type', '');
    }
    else {
      $type = $this->configuration['type'];
    }
    if (empty($type)) {
      $access_result = AccessResult::forbidden('No entity type provided.');
    }
    elseif ($access_result->isAllowed()) {
      $account = $account ?? $this->currentUser;
      [$entity_type_id, $bundle] = array_pad(explode(' ', $type, 2), 2, NULL);
      if ($bundle === NULL || $bundle === '' || $bundle === ContentEntityTypes::ALL) {
        $access_result = AccessResult::forbidden('Cannot determine access without a specified bundle.');
      }
      elseif (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        // @todo This should be taken care of by a submit validation handler.
        $access_result = AccessResult::forbidden(sprintf('Cannot determine access when "%s" is not a valid entity type ID.', $entity_type_id));
      }
      elseif (!$this->entityTypeManager->hasHandler($entity_type_id, 'access')) {
        $access_result = AccessResult::forbidden('Cannot determine access without an access handler.');
      }
      else {
        /**
         * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
         */
        $access_handler = $this->entityTypeManager->getHandler($entity_type_id, 'access');
        $access_result = $access_handler->createAccess($bundle, $account, [], TRUE);
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $config = &$this->configuration;
    if ($config['type'] === '_eca_token') {
      $type = $this->getTokenValue('type', '');
    }
    else {
      $type = $config['type'];
    }
    [$entity_type_id, $bundle] = explode(' ', $type);
    $values = [];
    $definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_keys = $definition->get('entity_keys');
    if (isset($entity_keys['bundle'])) {
      $values[$entity_keys['bundle']] = $bundle;
    }
    if (isset($entity_keys['langcode'])) {
      $langcode = trim($config['langcode']);
      if (in_array($langcode, ['_interface', ''], TRUE)) {
        $langcode = $this->languageManager->getCurrentLanguage()->getId();
      }
      elseif ($langcode === '_eca_token') {
        $langcode = $this->getTokenValue('langcode', $this->languageManager->getCurrentLanguage()->getId());
      }
      $values[$entity_keys['langcode']] = $langcode;
    }
    if (isset($entity_keys['label']) && isset($config['label'])) {
      $values[$entity_keys['label']] = trim((string) $this->tokenService->replace($config['label'], [], ['clear' => TRUE]));
    }
    if (isset($entity_keys['published'])) {
      $values[$entity_keys['published']] = (int) $this->shouldPublish();
    }
    if (isset($entity_keys['owner'])) {
      if (!empty($config['owner'])) {
        $owner_id = trim((string) $this->tokenService->replace($config['owner'], [], ['clear' => TRUE]));
      }
      if (!isset($owner_id) || $owner_id === '') {
        $owner_id = $this->currentUser->id();
      }
      $values[$entity_keys['owner']] = $owner_id;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    $this->entity = $entity;
    $this->tokenService->addTokenData($config['token_name'], $entity);
  }

  /**
   * Whether the new entity should be set as published or not.
   *
   * @return bool
   *   Returns TRUE in case to set as published, FALSE otherwise.
   */
  protected function shouldPublish(): bool {
    return $this->configuration['published'];
  }

}
