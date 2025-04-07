<?php

namespace Drupal\eca\Plugin\ECA\Event;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Base class for deriver classes that build ECA event plugins.
 */
abstract class EventDeriverBase extends DeriverBase {

  /**
   * Provides a list of plugin definitions.
   *
   * @return array
   *   List of definitions.
   */
  abstract protected function definitions(): array;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach ($this->definitions() as $definition_id => $definition) {
      $this->derivatives[$definition_id] = [
        'event_name' => $definition['event_name'],
        'subscriber_priority' => $definition['subscriber_priority'] ?? 0,
        'event_class' => $definition['event_class'],
        'action' => $definition_id,
        'label' => $definition['label'],
        'tags' => $definition['tags'] ?? 0,
      ] + $base_plugin_definition;
      if (isset($definition['description'])) {
        $this->derivatives[$definition_id]['description'] = $definition['description'];
      }
      if (isset($definition['eca_version_introduced'])) {
        $this->derivatives[$definition_id]['eca_version_introduced'] = $definition['eca_version_introduced'];
      }
    }
    return $this->derivatives;
  }

}
