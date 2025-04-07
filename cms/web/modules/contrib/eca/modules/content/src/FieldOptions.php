<?php

namespace Drupal\eca_content;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca_content\Event\OptionsSelection;

/**
 * Class for handling event-based field options.
 *
 * This class holds a callable for being invoked as allowed values function.
 * By using that callable, users may then react upon that within ECA and define
 * allowed values from there.
 * Example for a field storage configuration making use of that callback:
 *
 * @code
 * id: node.field_myoptionfield
 * field_name: field_myoptionfield
 * entity_type: node
 * type: list_string
 * settings:
 *   allowed_values: {  }
 *   allowed_values_function: 'Drupal\eca_content\FieldOptions::eventBasedValues'
 * @endcode
 */
final class FieldOptions {

  /**
   * Allowed values callback that triggers an event for collecting values.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The according entity.
   * @param bool &$cacheable
   *   The cacheable flag.
   */
  public static function eventBasedValues(FieldStorageDefinitionInterface $field_storage_definition, ?ContentEntityInterface $entity, bool &$cacheable): array {
    $cacheable = FALSE;
    $values = [];
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event = new OptionsSelection($field_storage_definition, $entity, $values);
    $event_dispatcher->dispatch($event, ContentEntityEvents::OPTIONS_SELECTION);
    return $event->allowedValues;
  }

}
