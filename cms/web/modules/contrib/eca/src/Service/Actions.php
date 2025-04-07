<?php

namespace Drupal\eca\Service;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Action\ActionInterface as CoreActionInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\eca\ErrorHandlerTrait;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\PluginManager\Action;
use Drupal\eca\Token\TokenInterface;

/**
 * Service class for Drupal core actions in ECA.
 */
class Actions {

  use ErrorHandlerTrait;
  use PluginFormTrait;
  use ServiceTrait;

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Entity type manager service.
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
   * The plugin ID.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Actions constructor.
   *
   * @param \Drupal\eca\PluginManager\Action $action_manager
   *   The action plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\eca\Token\TokenInterface $token_service
   *   The Token services.
   */
  public function __construct(Action $action_manager, LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, TokenInterface $token_service) {
    $this->actionManager = $action_manager->getDecoratedActionManager();
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
  }

  /**
   * Returns a sorted list of action plugins.
   *
   * @return \Drupal\Core\Action\ActionInterface[]
   *   The sorted list of actions.
   */
  public function actions(): array {
    $actions = &drupal_static('eca_actions');
    if ($actions === NULL) {
      $this->enableExtendedErrorHandling('Collecting all available actions');
      $actions = [];
      foreach ($this->actionManager->getDefinitions() as $plugin_id => $definition) {
        if (!empty($definition['confirm_form_route_name'])) {
          // We cannot support actions that redirect to a confirmation form.
          // @see https://www.drupal.org/project/eca/issues/3279483
          continue;
        }
        if ($definition['id'] === 'entity:save_action') {
          // We replace all save actions by one generic "Entity: save" action.
          continue;
        }
        if ($action = $this->createInstance($plugin_id)) {
          $actions[] = $action;
        }
      }
      $this->resetExtendedErrorHandling();
      $this->sortPlugins($actions);
    }
    return $actions;
  }

  /**
   * Get an action plugin by id.
   *
   * @param string $plugin_id
   *   The id of the action plugin to be returned.
   * @param array $configuration
   *   The optional configuration array.
   *
   * @return \Drupal\Core\Action\ActionInterface|null
   *   The action plugin.
   */
  public function createInstance(string $plugin_id, array $configuration = []): ?CoreActionInterface {
    try {
      /**
       * @var \Drupal\Core\Action\ActionInterface $action
       */
      $action = $this->actionManager->createInstance($plugin_id, $configuration);
    }
    catch (\Exception | \Throwable $e) {
      $action = NULL;
      $this->logger->error('The action plugin %pluginid can not be initialized. ECA is ignoring this action. The issue with this action: %msg', [
        '%pluginid' => $plugin_id,
        '%msg' => $e->getMessage(),
      ]);
    }
    return $action;
  }

  /**
   * Prepares all the fields of an action plugin for modellers.
   *
   * @param \Drupal\Core\Action\ActionInterface $action
   *   The action plugin for which the fields need to be prepared.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|null
   *   The list of fields for this action. If the plugin causes issues being
   *   loaded, this returns NULL.
   */
  public function getConfigurationForm(CoreActionInterface $action, FormStateInterface $form_state): ?array {
    $form = [];
    if ($action instanceof PluginFormInterface) {
      try {
        $form = $action->buildConfigurationForm([], $form_state);
      }
      catch (\Throwable | \AssertionError | \Exception $e) {
        $this->logger->error('The configuration form of %label action plugin can not be loaded. Plugin ignored. %message', [
          '%label' => $action->getPluginId(),
          '%message' => $e->getMessage(),
        ]);
        return NULL;
      }
    }
    elseif ($action instanceof ConfigurableInterface) {
      foreach ($action->defaultConfiguration() as $key => $value) {
        $form[$key] = [
          '#type' => 'textfield',
          '#title' => self::convertKeyToLabel($key),
          '#default_value' => $value,
        ];
      }
    }

    try {
      $actionType = $action->getPluginDefinition()['type'] ?? '';
      $actionConfig = ($action instanceof ConfigurableInterface) ? $action->getConfiguration() : [];
      if ($actionType === 'entity' || $this->entityTypeManager->getDefinition($actionType, FALSE)) {
        $form['object'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Entity'),
          '#description' => $this->t('Provide the token name of the %type that this action should operate with.', [
            '%type' => $actionType,
          ]),
          '#default_value' => $actionConfig['object'] ?? '',
          '#weight' => 2,
          '#eca_token_reference' => TRUE,
        ];
      }
      // Important: When adding checkbox fields, the extra field must be added
      // in Drupal\eca\Entity\Eca::validatePlugin().
      if (!($action instanceof ActionInterface) && ($action instanceof ConfigurableInterface)) {
        // @todo Consider a form validate and submit method for this service.
        $form['replace_tokens'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Replace tokens'),
          '#description' => $this->t('When enabled, tokens will be replaced <em>before</em> executing the action. <strong>Please note:</strong> Actions might already take care of replacing tokens on their own. Therefore, use this option only with care and when it makes sense.'),
          '#default_value' => $actionConfig['replace_tokens'] ?? FALSE,
          '#weight' => 5,
        ];
      }
    }
    catch (\Throwable | \AssertionError | \Exception $e) {
      $this->logger->error('There is an issue with the %label action plugin. Plugin ignored.', [
        '%label' => $action->getPluginId(),
      ]);
      return NULL;
    }

    $this->pluginId = $action->getPluginId();
    return $this->updateConfigurationForm($form);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

}
