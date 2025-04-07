<?php

namespace Drupal\eca\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Token\TokenInterface;

/**
 * Service class to calculate dependencies for ECA config entities.
 */
class DependencyCalculation {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $token;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * List of dependencies, calculated by this service.
   *
   * @var array
   */
  protected array $dependencies;

  /**
   * An initialized list of enabled calculations.
   *
   * @var string[]|null
   */
  protected static ?array $enabledCalculations = NULL;

  /**
   * Constructs the dependency calculation service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\eca\Token\TokenInterface $token
   *   The token service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityFieldManagerInterface $entityFieldManager, TokenInterface $token, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
    $this->token = $token;
    $this->configFactory = $config_factory;
  }

  /**
   * Calculates dependencies for the given ECA configuration.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA configuration entity to calculate dependencies for.
   *
   * @return array
   *   Calculated dependencies.
   */
  public function calculateDependencies(Eca $eca): array {
    if (!isset(self::$enabledCalculations)) {
      self::$enabledCalculations = $this->configFactory->get('eca.settings')->get('dependency_calculation') ?? [];
    }
    if (empty(self::$enabledCalculations)) {
      // Nothing to calculate.
      return [];
    }

    $dependencies = [];
    $events = $eca->get('events') ?? [];
    $entity_field_info = [];
    foreach ($events as $component) {
      $this->addDependenciesFromComponent($component, $entity_field_info, $eca, $dependencies);
    }
    return $dependencies;
  }

  /**
   * Adds dependencies by reading from the given component.
   *
   * @param array $component
   *   The component in the ECA config entity for which the dependencies
   *   should be calculated.
   * @param array $entity_field_info
   *   An array collecting entity field information.
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA configuration entity to calculate dependencies for.
   * @param array &$dependencies
   *   Current array of dependencies where new dependencies will be added to.
   * @param array &$scanned_ids
   *   An internal list of already scanned components, keyed by component ID.
   *   This list is mainly used to prevent infinite recursion.
   */
  protected function addDependenciesFromComponent(array $component, array &$entity_field_info, Eca $eca, array &$dependencies, array &$scanned_ids = []): void {
    $plugin_id_parts = !empty($component['plugin']) ? explode(':', $component['plugin']) : [];
    foreach ($plugin_id_parts as $id_part) {
      $this->addEntityFieldInfo($id_part, $entity_field_info);
    }
    if (!empty($component['configuration'])) {
      $this->addDependenciesFromFields($component['configuration'], $entity_field_info, $eca, $dependencies);
    }
    if (!empty($component['successors'])) {
      foreach ($component['successors'] as $successor) {
        if (!isset($successor['id']) || $successor['id'] === '' || isset($scanned_ids[$successor['id']])) {
          continue;
        }
        $successor_id = $successor['id'];
        foreach (['events', 'conditions', 'actions', 'gateways'] as $prop) {
          $components = $eca->get($prop) ?? [];
          if (isset($components[$successor_id])) {
            $scanned_ids[$successor_id] = 1;
            $this->addDependenciesFromComponent($components[$successor_id], $entity_field_info, $eca, $dependencies, $scanned_ids);
            break;
          }
        }
      }
    }
  }

  /**
   * Adds dependencies from values of plugin config fields.
   *
   * @param array $fields
   *   The config fields of an ECA-related plugin (event / condition / action).
   * @param array &$entity_field_info
   *   An array of collected entity field info, keyed by entity type ID.
   *   This array will be expanded by further entity types that will be
   *   additionally found.
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA configuration entity to calculate dependencies for.
   * @param array &$dependencies
   *   Current array of dependencies where new dependencies will be added to.
   */
  protected function addDependenciesFromFields(array $fields, array &$entity_field_info, Eca $eca, array &$dependencies): void {
    $variables = [];
    foreach ($fields as $name => $field) {
      if (!is_string($field)) {
        if (is_array($field)) {
          $this->addDependenciesFromFields($field, $entity_field_info, $eca, $dependencies);
        }
        continue;
      }

      if (mb_strpos('type', $name) !== FALSE) {
        [$field, $bundle] = array_merge(explode(' ', $field, 2), [ContentEntityTypes::ALL]);
      }
      else {
        preg_match_all('/
          [^\s\[\]\{\}:\.]+  # match type not containing whitespace : . [ ] { }
          [:\.]+             # separator (Token : or property path .)
          [^\s\[\]\{\}]+     # match name not containing whitespace [ ] { }
          /x', $field, $matches);
      }

      if (!isset($matches) || empty($matches[0])) {
        // Calling ::addEntityFieldInfo() here, so that the entity type is
        // present in case any subsequent plugin config field contains a field
        // name for that.
        $is_entity_type = $this->addEntityFieldInfo($field, $entity_field_info);
        if (!$is_entity_type && !in_array($field, $variables, TRUE)) {
          $variables[] = $field;
        }
        elseif ($is_entity_type) {
          $entity_type_id = $field;
          if (isset($bundle) && $bundle !== ContentEntityTypes::ALL && ($bundle_dependency = $this->entityTypeManager->getDefinition($entity_type_id)->getBundleConfigDependency($bundle))) {
            if (in_array('bundle', self::$enabledCalculations, TRUE)) {
              $this->addDependency($bundle_dependency['type'], $bundle_dependency['name'], $dependencies);
            }
          }
        }
      }
      else {
        foreach ($matches[0] as $variable) {
          if (!in_array($variable, $variables, TRUE)) {
            $variables[] = $variable;
          }
        }
        unset($matches);
      }
    }
    if (!in_array('field_storage', self::$enabledCalculations, TRUE)) {
      return;
    }
    foreach ($variables as $variable) {
      $variable_parts = mb_strpos($variable, ':') ? explode(':', $variable) : explode('.', $variable);
      foreach ($variable_parts as $variable_part) {
        if ($this->addEntityFieldInfo($variable_part, $entity_field_info)) {
          // Mapped to an entity type, thus no need for a field lookup.
          continue;
        }
        // Perform a lookup for used entity fields.
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');
        $info_item = end($entity_field_info);
        while ($info_item) {
          $entity_type_id = key($entity_field_info);
          if (isset($info_item[$variable_part])) {
            $field_name = $variable_part;
            // Found an existing field, add its storage config as dependency.
            // No break of the loop here, because any entity type that
            // possibly holds a field with that name should be considered,
            // as we cannot determine the underlying entity type of Token
            // aliases in a bulletproof way.
            $this->addDependency('config', $info_item[$field_name], $dependencies);
            if (in_array('field_config', self::$enabledCalculations, TRUE)) {
              // Include any field configuration from used bundles. Future
              // additions of fields and new bundles will be handled via hook
              // implementation.
              $bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
              foreach ($bundles as $bundle) {
                $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
                if (isset($field_definitions[$field_name])) {
                  $field_config_id = $entity_type_id . '.' . $bundle . '.' . $field_name;
                  /**
                   * @var \Drupal\field\FieldConfigInterface $field_config
                   */
                  if ($field_config = $field_config_storage->load($field_config_id)) {
                    $this->addDependency($field_config->getConfigDependencyKey(), 'field.field.' . $field_config->id(), $dependencies);
                  }
                }
              }
            }
          }
          $info_item = prev($entity_field_info);
        }
      }
    }
  }

  /**
   * Expands the field info array if the given variable is an entity type ID.
   *
   * @param string $variable
   *   The variable that is or is not an entity type ID.
   * @param array &$entity_field_info
   *   The current list of entity field info, sorted in reverse order by found
   *   entity types.
   *
   * @return bool
   *   Returns TRUE if the given variable was resolved to an entity type ID.
   */
  protected function addEntityFieldInfo(string $variable, array &$entity_field_info): bool {
    if (!($entity_type_id = $this->token->getEntityTypeForTokenType($variable))) {
      return FALSE;
    }
    $entity_type_manager = $this->entityTypeManager;
    if (isset($entity_field_info[$entity_type_id])) {
      // Put the info item at the end of the list, as we want to handle
      // found definitions by traversing in the reverse order they were found.
      $item = $entity_field_info[$entity_type_id];
      unset($entity_field_info[$entity_type_id]);
      $entity_field_info += [$entity_type_id => $item];
      return TRUE;
    }
    if ($entity_type_manager->hasDefinition($entity_type_id)) {
      $definition = $entity_type_manager->getDefinition($entity_type_id);
      $entity_field_info[$entity_type_id] = [];
      if ($definition->entityClassImplements(FieldableEntityInterface::class)) {
        foreach ($this->entityFieldManager->getFieldStorageDefinitions($entity_type_id) as $field_name => $storage_definition) {
          // Base fields don't have a manageable storage configuration, thus
          // they are excluded here.
          if (!$storage_definition->isBaseField()) {
            $entity_field_info[$entity_type_id][$field_name] = "field.storage.$entity_type_id.$field_name";
          }
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Adds a dependency to the given array of dependencies.
   *
   * @param string $type
   *   Type of dependency being added: 'module', 'theme', 'config', 'content'.
   * @param string $name
   *   If $type is 'module' or 'theme', the name of the module or theme. If
   *   $type is 'config' or 'content', the result of
   *   EntityInterface::getConfigDependencyName().
   * @param array &$dependencies
   *   Current array of dependencies where the dependency will be added to.
   */
  protected function addDependency(string $type, string $name, array &$dependencies): void {
    if (empty($dependencies[$type])) {
      $dependencies[$type] = [$name];
    }
    elseif (!in_array($name, $dependencies[$type], TRUE)) {
      $dependencies[$type][] = $name;
    }
  }

}
