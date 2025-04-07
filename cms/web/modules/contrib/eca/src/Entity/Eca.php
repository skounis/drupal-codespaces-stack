<?php

namespace Drupal\eca\Entity;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\eca\Entity\Objects\EcaAction;
use Drupal\eca\Entity\Objects\EcaEvent;
use Drupal\eca\Entity\Objects\EcaGateway;
use Drupal\eca\Entity\Objects\EcaObject;
use Drupal\eca\Form\RuntimePluginForm;
use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;
use Drupal\eca\Plugin\PluginUsageInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines the ECA entity type.
 *
 * @ConfigEntityType(
 *   id = "eca",
 *   label = @Translation("ECA"),
 *   label_collection = @Translation("ECAs"),
 *   label_singular = @Translation("ECA"),
 *   label_plural = @Translation("ECAs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ECA",
 *     plural = "@count ECAs",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\eca\Entity\EcaStorage",
 *   },
 *   config_prefix = "eca",
 *   admin_permission = "administer eca",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "modeller",
 *     "label",
 *     "uuid",
 *     "status",
 *     "version",
 *     "weight",
 *     "events",
 *     "conditions",
 *     "gateways",
 *     "actions"
 *   }
 * )
 */
class Eca extends ConfigEntityBase implements EntityWithPluginCollectionInterface {

  use EcaTrait;

  /**
   * List of action plugins for which validation needs to be avoided.
   *
   * @var string[]
   *
   * @see https://www.drupal.org/project/eca/issues/3278080
   */
  protected static array $ignoreConfigValidationActions = [
    'action_send_email_action',
    'node_assign_owner_action',
  ];

  /**
   * ID of the ECA config entity.
   *
   * @var string
   */
  protected string $id;

  /**
   * Label of the ECA config entity.
   *
   * @var string
   */
  protected string $label;

  /**
   * List of events.
   *
   * @var array
   */
  protected array $events = [];

  /**
   * List of conditions.
   *
   * @var array
   */
  protected array $conditions = [];

  /**
   * List of gateways.
   *
   * @var array|null
   */
  protected ?array $gateways = [];

  /**
   * List of actions.
   *
   * @var array
   */
  protected array $actions = [];

  /**
   * Model config entity for the ECA config entity.
   *
   * @var \Drupal\eca\Entity\Model
   */
  protected Model $model;

  /**
   * Whether this instance s in testing mode.
   *
   * @var bool
   */
  protected static bool $isTesting = FALSE;

  /**
   * Set the instance into testing mode.
   *
   * This will prevent dependency calculation which would fail during test setup
   * if not all dependant config entities were available from the test module
   * itself.
   *
   * Problem is, that we can't add all the config dependencies to the test
   * modules, because that would fail if we enable the test modules in a real
   * Drupal instance, as some of those config entities already exist from
   * core modules.
   */
  public static function setTesting(): void {
    static::$isTesting = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities): void {
    parent::postLoad($storage, $entities);
    /** @var \Drupal\eca\Entity\Eca $entity */
    foreach ($entities as $entity) {
      if ($entity->get('weight') === NULL) {
        $entity->set('weight', 0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    foreach ([
      'events' => $this->eventPluginManager(),
      'conditions' => $this->conditionPluginManager(),
      'actions' => $this->actionPluginManager(),
    ] as $plugins => $manager) {
      foreach ($this->{$plugins} as $id => $pluginDef) {
        $plugin = $manager->createInstance($pluginDef['plugin'], $pluginDef['configuration']);
        // Allows ECA plugins to react upon being added to an ECA entity.
        if ($plugin instanceof PluginUsageInterface) {
          $plugin->pluginUsed($this, $id);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // As ::trustData() states that dependencies are not calculated on save,
    // calculation is skipped when flagged as trusted.
    // @see Drupal\Core\Config\Entity\ConfigEntityInterface::trustData
    if (static::$isTesting || $this->trustedData) {
      return $this;
    }
    parent::calculateDependencies();
    foreach ($this->dependencyCalculation()->calculateDependencies($this) as $type => $names) {
      foreach ($names as $name) {
        $this->addDependency($type, $name);
      }
    }
    return $this;
  }

  /**
   * Builds the cache ID for an ID inside this ECA config entity.
   *
   * @param string $id
   *   An idea for which a cache ID inside this ECA config entity is needed.
   *
   * @return string
   *   The cache ID.
   */
  protected function buildCacheId(string $id): string {
    return "eca:$this->id:$id";
  }

  /**
   * Determines if ECA validation is being disabled for the current request.
   *
   * @return bool
   *   TRUE, if the current request has a query argument eca_validation set to
   *   off, FALSE otherwise.
   */
  protected function isValidationDisabled(): bool {
    $request = $this->request();
    // @noinspection StrContainsCanBeUsedInspection
    $isAjax = mb_strpos($request->get(MainContentViewSubscriber::WRAPPER_FORMAT, ''), 'drupal_ajax') !== FALSE;
    if ($isAjax && ($referer = $request->headers->get('referer')) && $query = parse_url($referer, PHP_URL_QUERY)) {
      // @noinspection StrContainsCanBeUsedInspection
      return mb_strpos($query, 'eca_validation=off') !== FALSE;
    }
    return $request->query->get('eca_validation', '') === 'off';
  }

  /**
   * Determine if the ECA config entity is editable.
   *
   * @return bool
   *   If the associated modeller supports editing inside the Drupal admin UI,
   *   return TRUE, FALSE otherwise.
   */
  public function isEditable(): bool {
    if ($modeller = $this->getModeller()) {
      return $modeller->isEditable();
    }
    return FALSE;
  }

  /**
   * Provides the modeller plugin associated with this ECA config entity.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface|null
   *   Returns the modeller plugin if possible, NULL otherwise.
   */
  public function getModeller(): ?ModellerInterface {
    try {
      /**
       * @var \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface $plugin
       */
      $plugin = $this->modellerPluginManager()->createInstance($this->get('modeller'));
    }
    catch (PluginException $e) {
      $this->logger()->error($e->getMessage());
      return NULL;
    }
    $plugin->setConfigEntity($this);
    return $plugin;
  }

  /**
   * Determines if the current ECA model has model data.
   *
   * @return bool
   *   TRUE, if the current ECA has model data, FALSE otherwise.
   */
  public function hasModel(): bool {
    return $this->getModel()->getModeldata() !== '';
  }

  /**
   * Provides the ECA model entity storing the data for this ECA config entity.
   *
   * @return \Drupal\eca\Entity\Model
   *   The ECA model entity.
   */
  public function getModel(): Model {
    if (!isset($this->model)) {
      try {
        $storage = $this->entityTypeManager()->getStorage('eca_model');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // @todo Log this exception.
        // This should be impossible to ever happen, because this module is
        // providing that storage handler.
        return $this->model;
      }
      /**
       * @var \Drupal\eca\Entity\Model|null $model
       */
      $model = $storage->load($this->id());
      if ($model === NULL) {
        /**
         * @var \Drupal\eca\Entity\Model $model
         */
        $model = $storage->create([
          'id' => $this->id(),
        ]);
      }
      $this->model = $model;
    }
    return $this->model;
  }

  /**
   * Reset all component (events, conditions, actions, gateways) arrays.
   *
   * This should be called by the modeller once before the methods
   * ::addEvent, ::addCondition, ::addAction or ::addGateway will be used.
   */
  public function resetComponents(): void {
    $this->events = [];
    $this->conditions = [];
    $this->actions = [];
    $this->gateways = [];
  }

  /**
   * Add a condition item to this ECA config entity.
   *
   * @param string $id
   *   The condition ID.
   * @param string $plugin_id
   *   The condition's plugin ID.
   * @param array $fields
   *   The configuration for this condition.
   *
   * @return bool
   *   Returns TRUE if the condition's configuration is valid, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   When the given condition plugin ID does not exist.
   */
  public function addCondition(string $id, string $plugin_id, array $fields): bool {
    $plugin = $this->conditionPluginManager()->createInstance($plugin_id, []);
    if (($plugin instanceof PluginFormInterface) && !$this->validatePlugin($plugin, $fields, 'condition', $plugin_id, $id)) {
      return FALSE;
    }

    $this->conditions[$id] = [
      'plugin' => $plugin_id,
      'configuration' => $fields,
    ];
    return TRUE;
  }

  /**
   * Add a gateway item to this ECA config entity.
   *
   * @param string $id
   *   The gateway ID.
   * @param int $type
   *   The gateway type.
   * @param array $successors
   *   A list of successor items linked to this gateway.
   *
   * @return bool
   *   Returns TRUE if the gateway was successfully added, FALSE otherwise.
   */
  public function addGateway(string $id, int $type, array $successors): bool {
    $this->gateways[$id] = [
      'type' => $type,
      'successors' => $successors,
    ];
    return TRUE;
  }

  /**
   * Add an event item to this ECA config entity.
   *
   * @param string $id
   *   The event ID.
   * @param string $plugin_id
   *   The event's plugin ID.
   * @param string $label
   *   The event label.
   * @param array $fields
   *   The configuration for this event.
   * @param array $successors
   *   A list of successor items linked to this event.
   *
   * @return bool
   *   Returns TRUE if the event's configuration is valid, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   When the given event plugin ID does not exist.
   */
  public function addEvent(string $id, string $plugin_id, string $label, array $fields, array $successors): bool {
    $plugin = $this->eventPluginManager()->createInstance($plugin_id, []);
    if (($plugin instanceof PluginFormInterface) && !$this->validatePlugin($plugin, $fields, 'event', $plugin_id, $label)) {
      return FALSE;
    }

    if (empty($label)) {
      $label = $id;
    }
    $this->events[$id] = [
      'plugin' => $plugin_id,
      'label' => $label,
      'configuration' => $fields,
      'successors' => $successors,
    ];
    return TRUE;
  }

  /**
   * Add an action item to this ECA config entity.
   *
   * As action plugins are controlled by Drupal core's action plugin manager
   * and not by ECA, this method will run new actions through the configuration
   * form validation and submission and validates, if the given configuration
   * is valid.
   *
   * @param string $id
   *   The action ID.
   * @param string $plugin_id
   *   The action's plugin ID.
   * @param string $label
   *   The action label.
   * @param array $fields
   *   The configuration for this action.
   * @param array $successors
   *   A list of successor items linked to this action.
   *
   * @return bool
   *   Returns TRUE if the action's configuration is valid, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   When the given action plugin ID does not exist.
   */
  public function addAction(string $id, string $plugin_id, string $label, array $fields, array $successors): bool {
    $plugin = $this->actionPluginManager()->createInstance($plugin_id, []);
    if (($plugin instanceof PluginFormInterface) && !$this->validatePlugin($plugin, $fields, 'action', $plugin_id, $label)) {
      return FALSE;
    }

    if (empty($label)) {
      $label = $id;
    }
    $this->actions[$id] = [
      'plugin' => $plugin_id,
      'label' => $label,
      'configuration' => $fields,
      'successors' => $successors,
    ];
    return TRUE;
  }

  /**
   * Validate the configuration of an event, condition or action plugin.
   *
   * @param \Drupal\Core\Plugin\PluginFormInterface $plugin
   *   The plugin to be validated.
   * @param array $fields
   *   The configuration values to be validated.
   * @param string $type
   *   The plugin type, either event, condition or action.
   * @param string $plugin_id
   *   The plugin id.
   * @param string $label
   *   The label in the model.
   *
   * @return bool
   *   TRUE, if configuration form validation has no errors. FALSE otherwise.
   */
  protected function validatePlugin(PluginFormInterface $plugin, array &$fields, string $type, string $plugin_id, string $label): bool {
    $replaced_fields = [];
    $eca_validation_error = FALSE;
    $messenger = $this->messenger();
    $plugin_label = $plugin instanceof PluginInspectionInterface ?
      $plugin->getPluginDefinition()['label'] : 'unknown';

    if ($plugin instanceof ConfigurableInterface) {
      foreach ($plugin->defaultConfiguration() + ['replace_tokens' => FALSE] as $key => $value) {
        // Convert potential strings from pseudo-checkboxes (for example a
        // dropdown with "yes" or "no" options).
        if (is_bool($value) &&
          isset($fields[$key]) &&
          is_string($fields[$key]) &&
          in_array(mb_strtolower($fields[$key]), ['yes', 'no'], TRUE)
        ) {
          if (mb_strtolower($fields[$key]) === 'yes') {
            $fields[$key] = TRUE;
          }
          else {
            // Unset from the fields array. An unchecked checkbox is like
            // no value is provided on form submission.
            unset($fields[$key]);
            // When plugin configuration is being used on form building,
            // the default value will be used. This makes sure, that the
            // default value is treated like an unchecked checkbox.
            $plugin->setConfiguration([$key => FALSE] + $plugin->getConfiguration());
          }
        }
      }

      // Identify number or email fields and replace them with a valid value if
      // the field is configured with a token. This is important to get those
      // fields through form validation without issues.
      // @todo Add support for nested form fields like e.g. in container/fieldset.
      $form = [];
      $form_state = new FormState();
      foreach ($plugin->buildConfigurationForm($form, $form_state) as $key => $form_field) {
        if (!empty($form_field['#eca_token_reference']) &&
          isset($fields[$key]) &&
          $this->valueIsToken($fields[$key])
        ) {
          $eca_validation_error = TRUE;
          $errorMsg = sprintf('%s "%s" (%s): %s', $type, $plugin_label, $label, 'This field requires a token name, not a token; please remove the brackets.');
          $messenger->addError($errorMsg);
        }
        if (!empty($form_field['#eca_token_select_option']) && isset($form_field['#options']) && is_array($form_field['#options']) && ($fields[$key] === '_eca_token' || $fields[$key] === '')) {
          // Remember the original configuration value.
          $replaced_fields[$key] = $fields[$key];
          $fields[$key] = array_key_first($form_field['#options']);
        }
        if (isset($form_field['#type'], $fields[$key]) &&
          in_array($form_field['#type'], ['number', 'email', 'machine_name'], TRUE) &&
          $this->valueIsToken($fields[$key])
        ) {
          // Remember the original configuration value.
          $replaced_fields[$key] = $fields[$key];

          switch ($form_field['#type']) {

            case 'number':
              // Set a valid value for the form element type 'number'
              // to pass the validation. Also if the field is required
              // the value "0" would cause a form error, let's use "1" instead.
              $fields[$key] = $form_field['#min'] ?? 1;
              break;

            case 'email':
              // Set a valid value for the form element type 'email'
              // to pass validation.
              $fields[$key] = 'lorem@eca.local';
              break;

            case 'machine_name':
              // Set a valid value for the form element type 'machine_name'
              // to pass validation. Needs to append a random value, so that
              // it passes "exists" callbacks.
              $fields[$key] = 'eca_' . mb_strtolower((new Random())->name(8, TRUE));
              break;

          }
        }
        if (isset($form_field['#type'], $fields[$key]) && $form_field['#type'] === 'machine_name') {
          // Remember the original configuration value.
          $replaced_fields[$key] = $fields[$key];
          $fields[$key] = str_replace('][', '', $fields[$key]);
        }
      }
    }

    // Simulate filling and submitting a form for configuring the plugin.
    $form_state = new FormState();
    $form_state->setProgrammed();
    $form_state->setSubmitted();

    if (!in_array($plugin_id, self::$ignoreConfigValidationActions, TRUE)) {
      // Build a runtime form for validating the plugin.
      $form_object = new RuntimePluginForm($plugin);

      // Runtime plugin form uses a subform state for the plugin configuration.
      $form_state->setUserInput(['configuration' => $fields]);
      $form_state->setValues(['configuration' => $fields]);

      // Keep the currently stored list of messages in mind.
      // The form build will add messages to the messenger, which we want
      // to clear from the runtime.
      $messages_by_type = $messenger->all();

      // Keep the current "has any errors" flag in mind, and reset this flag
      // for the scope of this operation.
      $any_errors = FormState::hasAnyErrors();
      $form_state->clearErrors();

      // Building the form also submits the form, if no errors are there.
      $form = $this->formBuilder()->buildForm($form_object, $form_state);

      // Now re-add the previously fetched messages.
      $messenger->deleteAll();
      foreach ($messages_by_type as $messageType => $messages) {
        foreach ($messages as $message) {
          $messenger->addMessage($message, $messageType);
        }
      }

      // Check for errors.
      if ($errors = $form_state->getErrors()) {
        foreach ($errors as $error) {
          $errorMsg = sprintf('%s "%s" (%s): %s', $type, $plugin_label, $label, $error);
          $messenger->addError($errorMsg);
        }
        return FALSE;
      }

      if ($any_errors) {
        // Make sure that the form state will have the any errors flag restored.
        (new FormState())->setErrorByName('');
      }
    }
    else {
      // Build and execute submit handlers. This makes sure that submit handlers
      // have properly set configuration values.
      $form_state->setUserInput($fields);
      $form_state->setValues($fields);
      $form = $plugin->buildConfigurationForm([], $form_state);
      $plugin->submitConfigurationForm($form, $form_state);
    }

    // If there have been any ECA specific validation errors but no other form
    // error, we end up here but won't proceed, as the model is not valid.
    if ($eca_validation_error) {
      return FALSE;
    }

    // Collect the resulting form field values.
    $fields = ($plugin instanceof ConfigurableInterface ? $plugin->getConfiguration() : []) + $fields;

    // Restore tokens for numeric configuration fields.
    foreach ($replaced_fields as $key => $original_value) {
      $fields[$key] = $original_value;
    }
    return TRUE;
  }

  /**
   * Returns a list of info strings about included events in this ECA model.
   *
   * @return array
   *   A list of info strings about included events in this ECA model.
   */
  public function getEventInfos(): array {
    $events = [];
    foreach ($this->getUsedEvents() as $used_event) {
      $events[] = $this->getEventInfo($used_event);
    }
    return $events;
  }

  /**
   * Returns an info string about the ECA event.
   *
   * @return string
   *   The info string.
   */
  public function getEventInfo(EcaEvent $ecaEvent): string {
    $plugin = $ecaEvent->getPlugin();
    $event_info = $plugin->getPluginDefinition()['label'];
    // If available, additionally display the first config value of the event.
    if ($event_config = $ecaEvent->getConfiguration()) {
      $first_key = key($event_config);
      $first_value = current($event_config);
      $form = $plugin->buildConfigurationForm([], new FormState());
      if (isset($form[$first_key]['#options'][$first_value])) {
        $first_value = $form[$first_key]['#options'][$first_value];
      }
      $event_info .= ' (' . $first_value . ')';
    }
    return $event_info;
  }

  /**
   * Provides a list of all used events by this ECA config entity.
   *
   * @param array|null $ids
   *   (optional) When set, only the subset of given event object IDs are being
   *   returned.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent[]
   *   The list of used events.
   */
  public function getUsedEvents(?array $ids = NULL): array {
    $events = [];
    $ids = $ids ?? array_keys($this->events);
    foreach ($ids as $id) {
      if (!isset($this->events[$id])) {
        continue;
      }
      $def = &$this->events[$id];
      /** @var \Drupal\eca\Entity\Objects\EcaEvent|null $event */
      $event = $this->getEcaObject('event', $def['plugin'], $id, $def['label'] ?? 'noname', $def['configuration'] ?? [], $def['successors'] ?? []);
      if ($event) {
        $events[$id] = $event;
      }
      unset($def);
    }
    return $events;
  }

  /**
   * Get a single ECA event object.
   *
   * @param string $id
   *   The ID of the event object within this ECA configuration.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent|null
   *   The ECA event object, or NULL if not found.
   */
  public function getEcaEvent(string $id): ?EcaEvent {
    return current($this->getUsedEvents([$id])) ?: NULL;
  }

  /**
   * Get the used conditions.
   *
   * @return array
   *   List of used conditions.
   */
  public function getConditions(): array {
    return $this->conditions;
  }

  /**
   * Get the used actions.
   *
   * @return array
   *   List of used action.
   */
  public function getActions(): array {
    return $this->actions;
  }

  /**
   * Provides a list of valid successors to any ECA item in a given context.
   *
   * @param \Drupal\eca\Entity\Objects\EcaObject $eca_object
   *   The ECA item, for which the successors are requested.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The originally triggered event in which context to determine the list
   *   of valid successors.
   * @param array $context
   *   A list of tokens from the current context to be used for meaningful
   *   log messages.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject[]
   *   The list of valid successors.
   */
  public function getSuccessors(EcaObject $eca_object, Event $event, array $context): array {
    $successors = [];
    foreach ($eca_object->getSuccessors() as $successor) {
      $context['%successorid'] = $successor['id'];
      if ($action = $this->actions[$successor['id']] ?? FALSE) {
        $context['%successorlabel'] = $action['label'] ?? 'noname';
        $this->logger()->debug('Check action successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        if ($successorObject = $this->getEcaObject('action', $action['plugin'], $successor['id'], $action['label'] ?? 'noname', $action['configuration'] ?? [], $action['successors'] ?? [], $eca_object->getEvent())) {
          if ($this->conditionServices()->assertCondition($event, $successor['condition'], $this->conditions[$successor['condition']] ?? NULL, $context)) {
            $successors[] = $successorObject;
          }
        }
        else {
          $this->logger()->error('Invalid action successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        }
      }
      elseif ($gateway = $this->gateways[$successor['id']] ?? FALSE) {
        $context['%successorlabel'] = $gateway['label'] ?? 'noname';
        $this->logger()->debug('Check gateway successor %successorlabel (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        $successorObject = new EcaGateway($this, $successor['id'], $gateway['label'] ?? 'noname', $eca_object->getEvent(), $gateway['type']);
        $successorObject->setSuccessors($gateway['successors']);
        if ($this->conditionServices()->assertCondition($event, $successor['condition'], $this->conditions[$successor['condition']] ?? NULL, $context)) {
          $successors[] = $successorObject;
        }
      }
      else {
        $this->logger()->error('Non existent successor (%successorid) from ECA %ecalabel (%ecaid) for event %event.', $context);
      }
    }
    return $successors;
  }

  /**
   * Provides an ECA item build from given properties.
   *
   * @param string $type
   *   The ECA object type. Can bei either "event" or "action".
   * @param string $plugin_id
   *   The plugin ID.
   * @param string $id
   *   The item ID given by the modeller.
   * @param string $label
   *   The label.
   * @param array $fields
   *   The configuration of the item.
   * @param array $successors
   *   The list of associated successors.
   * @param \Drupal\eca\Entity\Objects\EcaEvent|null $event
   *   The original ECA event object, if looking for an action, NULL otherwise.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject|null
   *   The ECA object if available, NULL otherwise.
   */
  private function getEcaObject(string $type, string $plugin_id, string $id, string $label, array $fields, array $successors, ?EcaEvent $event = NULL): ?EcaObject {
    $ecaObject = NULL;
    switch ($type) {
      case 'event':
        try {
          /**
           * @var \Drupal\eca\Plugin\ECA\Event\EventInterface $plugin
           */
          $plugin = $this->eventPluginManager()->createInstance($plugin_id, $fields);
        }
        catch (PluginException $e) {
          // This can be ignored.
        }
        if (isset($plugin)) {
          $ecaObject = new EcaEvent($this, $id, $label, $plugin);
        }
        break;

      case 'action':
        if ($event !== NULL) {
          try {
            /**
             * @var \Drupal\Core\Action\ActionInterface $plugin
             */
            $plugin = $this->actionPluginManager()->createInstance($plugin_id, $fields);
          }
          catch (PluginException $e) {
            // This can be ignored.
          }
          if (isset($plugin)) {
            $ecaObject = new EcaAction($this, $id, $label, $event, $plugin);
          }
        }
        break;

    }
    if ($ecaObject !== NULL) {
      foreach ($fields as $key => $value) {
        $ecaObject->setConfiguration($key, $value);
      }
      $ecaObject->setSuccessors($successors);
      return $ecaObject;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    $collections = [];
    if ($this->isValidationDisabled()) {
      return $collections;
    }
    if (!empty($this->events)) {
      foreach ($this->events as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['events.' . $id] = new DefaultSingleLazyPluginCollection($this->eventPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    if (!empty($this->conditions)) {
      foreach ($this->conditions as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['conditions.' . $id] = new DefaultSingleLazyPluginCollection($this->conditionPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    if (!empty($this->actions)) {
      foreach ($this->actions as $id => $info) {
        if (empty($info['plugin'])) {
          continue;
        }
        $collections['actions.' . $id] = new DefaultSingleLazyPluginCollection($this->actionPluginManager(), $info['plugin'], $info['configuration'] ?? []);
      }
    }
    return $collections;
  }

  /**
   * Adds a dependency that could only be calculated on runtime.
   *
   * After adding a dependency on runtime, this configuration should be saved.
   *
   * @param string $type
   *   Type of dependency being added: 'module', 'theme', 'config', 'content'.
   * @param string $name
   *   If $type is 'module' or 'theme', the name of the module or theme. If
   *   $type is 'config' or 'content', the result of
   *   EntityInterface::getConfigDependencyName().
   *
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   *
   * @return static
   *   The ECA config itself.
   */
  public function addRuntimeDependency(string $type, string $name): Eca {
    $this->addDependency($type, $name);
    return $this;
  }

  /**
   * Checks if a given value has the patterns of a token.
   *
   * @param string $value
   *   The field value.
   *
   * @return bool
   *   Wether TRUE or FALSE based on the pattern.
   */
  protected function valueIsToken($value): bool {
    return (mb_substr((string) $value, 0, 1) === '[') &&
    (mb_substr((string) $value, -1, 1) === ']') &&
    (mb_strlen((string) $value) <= 255);
  }

}
