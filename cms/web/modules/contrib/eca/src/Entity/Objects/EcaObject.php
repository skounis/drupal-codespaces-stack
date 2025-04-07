<?php

namespace Drupal\eca\Entity\Objects;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Action\ActionInterface as CoreActionInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\EcaTrait;
use Drupal\eca\Event\EntityEventInterface;
use Drupal\eca\Event\FormEventInterface;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for ECA items used to internally process them.
 */
abstract class EcaObject {

  use EcaTrait;

  /**
   * ECA config entity.
   *
   * @var \Drupal\eca\Entity\Eca
   */
  protected Eca $eca;

  /**
   * ECA event object which started the process towards this item.
   *
   * @var \Drupal\eca\Entity\Objects\EcaEvent
   */
  protected EcaEvent $event;

  /**
   * The most recent set predecessor.
   *
   * @var \Drupal\eca\Entity\Objects\EcaObject|null
   */
  protected ?EcaObject $predecessor;

  /**
   * Item configuration.
   *
   * @var array
   */
  protected array $configuration = [];

  /**
   * List of successors.
   *
   * @var array
   */
  protected array $successors = [];

  /**
   * Item ID provided by the modeller.
   *
   * @var string
   */
  protected string $id;

  /**
   * Item label.
   *
   * @var string
   */
  protected string $label;

  /**
   * A static list of key fields that possibly hold a token.
   *
   * @var array
   */
  protected static array $keyFields = ['entity', 'object'];

  /**
   * Constructor.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $id
   *   The item ID provided by the modeller.
   * @param string $label
   *   The item label.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $event
   *   The ECA event object which started the process towards this item.
   */
  public function __construct(Eca $eca, string $id, string $label, EcaEvent $event) {
    $this->eca = $eca;
    $this->id = $id;
    $this->label = $label;
    $this->event = $event;
  }

  /**
   * Provides the ECA config entity.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The ECA config entity.
   */
  public function getEca(): Eca {
    return $this->eca;
  }

  /**
   * Provides ECA event object which started the process towards this item.
   *
   * @return \Drupal\eca\Entity\Objects\EcaEvent
   *   The ECA event.
   */
  public function getEvent(): EcaEvent {
    return $this->event;
  }

  /**
   * Get the configuration of this object.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * Sets a configuration value.
   *
   * @param string $key
   *   The key of the configuration item to be set.
   * @param mixed $value
   *   The value for that configuration item.
   *
   * @return $this
   */
  public function setConfiguration(string $key, mixed $value): EcaObject {
    $this->configuration[$key] = $value;
    return $this;
  }

  /**
   * Sets the list of successors of this item.
   *
   * @param array $successors
   *   The list of successors.
   *
   * @return $this
   */
  public function setSuccessors(array $successors): EcaObject {
    $this->successors = $successors;
    return $this;
  }

  /**
   * Get the list of successors.
   *
   * @return array
   *   The list of successors.
   */
  public function getSuccessors(): array {
    return $this->successors;
  }

  /**
   * Get the item ID provided by the modeller.
   *
   * @return string
   *   The item ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the item label.
   *
   * @return string
   *   The item label.
   */
  public function getLabel(): string {
    return $this->label ?? 'noname';
  }

  /**
   * Default implementation to execute the item.
   *
   * This should be overwritten by items with more specific instructions.
   *
   * @param \Drupal\eca\Entity\Objects\EcaObject|null $predecessor
   *   The item proceeding this one. May be null when this object the root item.
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The event that was originally triggered.
   * @param array $context
   *   List of key value pairs, used to generate meaningful log messages.
   *
   * @return bool
   *   TRUE, if the item was executed, FALSE otherwise.
   */
  public function execute(?EcaObject $predecessor, Event $event, array $context): bool {
    $this->predecessor = $predecessor;
    return TRUE;
  }

  /**
   * Returns the applicable data objects for the given plugin.
   *
   * The plugin is either an action or a condition plugin and depending on their
   * type property, this method determines which is the correct data object
   * upon which the action should execute or condition should assert.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The action or condition plugin for which the data object is required.
   *
   * @return array
   *   The appropriate data objects for the given plugin in the current context.
   *   The returned array may contain \Symfony\Contracts\EventDispatcher\Event,
   *   \Drupal\Core\Entity\EntityInterface or NULL values.
   */
  public function getObjects(PluginInspectionInterface $plugin): array {
    $actionType = $plugin->getPluginDefinition()['type'] ?? '';
    switch ($actionType) {
      case NULL:
      case '':
        // The plugin doesn't provide any type declaration, it doesn't require
        // any data object for that matter then.
        return [NULL];

      case 'form':
        // The plugin executes upon a form event and this will determine
        // the correct form event to be returned.
        return [$this->getFormEvent($plugin)];

      case 'system':
      case 'entity':
        // The plugin executes upon an entity and this will determine the
        // correct entity (or multiple entities) for the current context.
        return $this->getEntities($plugin) + [NULL];

    }
    // The plugin declares another type, i.e. none of the above. If the
    // given type is an entity type ID and the context provides an entity
    // of that given entity type, this is then the required one and will
    // be returned.
    $entities = [];
    foreach ($this->getEntities($plugin) as $entity) {
      if ($entity->getEntityTypeId() === $actionType) {
        $entities[] = $entity;
      }
    }
    return $entities + [NULL];
  }

  /**
   * Determine the correct entities for the $plugin in the current context.
   *
   * If the plugin is configurable and an entity is being declared as the
   * required one by a set key field, this will grab that object from the token
   * service using the defined key and returns it.
   *
   * If the plugin does not request a specific object, the following lookups
   * will be performed (only for actions and conditions):
   * - Check if the plugin ID contains a hint to the used entity / token type.
   * - Ask predecessor(s) for having a previously declared object. If the
   *   nearest predecessor has one, it will be returned.
   * - As a last resort, ask the triggering event for an entity and return it.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The action or condition plugin for which the data object is required.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The required entities if available.
   *   May return an empty array if no entity was found.
   */
  private function getEntities(PluginInspectionInterface $plugin): array {
    if ($plugin instanceof ConfigurableInterface) {
      $config = $plugin->getConfiguration();
    }
    elseif (isset($plugin->configuration)) {
      $config = $plugin->configuration;
    }
    elseif (isset($this->configuration)) {
      $config = $this->configuration;
    }

    $token = $this->token();

    // If the plugin is configurable and an entity is being declared as the
    // required one by a set key field, this will grab that object from the
    // token service using the defined key and returns it.
    if (!empty($config)) {
      foreach (static::$keyFields as $key_field) {
        if (isset($config[$key_field]) && is_string($config[$key_field]) && trim($config[$key_field]) !== '' && $data = $this->filterEntities($token->getTokenData($config[$key_field]))) {
          return $data;
        }
      }
    }

    if ($plugin instanceof ActionInterface || $plugin instanceof CoreActionInterface || $plugin instanceof ConditionInterface) {
      // Check if the plugin ID contains a hint to the entity to use.
      $id_parts = explode(':', $plugin->getPluginId());
      while ($id_part = array_pop($id_parts)) {
        if ($data = $this->filterEntities($token->getTokenData($id_part))) {
          return $data;
        }
        if (($type = $token->getTokenTypeForEntityType($id_part)) && $type !== $id_part && ($data = $this->filterEntities($token->getTokenData($type)))) {
          return $data;
        }
      }

      // Check if the plugin type contains a hint to the entity to use.
      $definition = $plugin->getPluginDefinition();
      if (isset($definition['type']) && is_string($definition['type']) && ($type = $token->getTokenTypeForEntityType($definition['type'])) && ($data = $this->filterEntities($token->getTokenData($type)))) {
        return $data;
      }

      // Ask predecessor(s) for having previously declared entities.
      $predecessor = $this->predecessor ?? NULL;
      if ($predecessor instanceof ObjectWithPluginInterface && $predecessor instanceof self && $objects = $this->filterEntities($predecessor->getObjects($predecessor->getPlugin()))) {
        return $objects;
      }

      if (method_exists($plugin, 'getEvent')) {
        // As a last resort, ask the triggering event for an entity.
        $event = $plugin->getEvent();
        if ($event instanceof EntityEventInterface) {
          return [$event->getEntity()];
        }
        if ($event instanceof FormEventInterface) {
          $form_object = $event->getFormState()->getFormObject();
          if ($form_object instanceof EntityFormInterface) {
            return [$form_object->getEntity()];
          }
        }
      }
    }
    return [];
  }

  /**
   * Determine the correct form event for the $plugin in the current context.
   *
   * If the plugin is being executed in the context of a form event, that
   * event will be returned such that the plugin can later retrieve the form
   * and formState objects from that event for further processing.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The action or condition plugin for which the data object is required.
   *
   * @return \Drupal\eca\Event\FormEventInterface|null
   *   The required form event if available or NULL otherwise.
   */
  private function getFormEvent(PluginInspectionInterface $plugin): ?FormEventInterface {
    if ($plugin instanceof ActionInterface || $plugin instanceof ConditionInterface) {
      $event = $plugin->getEvent();
      if ($event instanceof FormEventInterface) {
        return $event;
      }
    }
    return NULL;
  }

  /**
   * Helper method that returns an array that only contains entities.
   *
   * @param mixed $data
   *   The data to filter.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The array containing only entities (maybe empty).
   */
  private function filterEntities(mixed $data): array {
    if ($data instanceof EntityInterface) {
      return [$data];
    }
    if ($data instanceof EntityReferenceFieldItemListInterface) {
      return array_values($data->referencedEntities());
    }
    if ($data instanceof EntityReferenceItem) {
      /**
       * @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parent
       */
      $parent = $data->getParent();
      $entities = $parent->referencedEntities();
      foreach ($parent as $delta => $item) {
        if ($item === $data) {
          return [$entities[$delta]];
        }
      }
    }
    if ($data instanceof EntityAdapter) {
      $data = [$data];
    }

    $entities = [];
    if (is_iterable($data)) {
      foreach ($data as $value) {
        if ($value instanceof TypedDataInterface) {
          $value = $value->getValue();
        }
        if ($value instanceof EntityInterface && !in_array($value, $entities, TRUE)) {
          $entities[] = $value;
        }
      }
    }
    return $entities;
  }

}
