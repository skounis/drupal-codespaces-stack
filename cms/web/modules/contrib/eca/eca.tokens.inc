<?php

/**
 * @file
 * Tokens provided by ECA.
 */

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Token\TokenServices;

/**
 * Implements hook_token_info().
 */
function eca_token_info(): array {
  $info = [];
  $info['types']['dto'] = [
    'name' => t('DTO'),
    'description' => t('Tokens containing arbitrary data (Data Transfer Objects).'),
    'needs-data' => 'dto',
    'nested' => TRUE,
    'dynamic' => TRUE,
  ];
  $info['types']['_eca_root_token'] = [
    'name' => t('ECA root-level token'),
    'description' => t('Support for tokens to access data on their root level, for example [list].'),
    'nested' => TRUE,
    'dynamic' => TRUE,
  ];
  $info['types']['plain'] = [
    'name' => t('Plain value'),
    'description' => t('Get the plain text value for any available token, without escaping HTML characters.'),
  ];
  // @see https://www.drupal.org/project/eca/issues/3306180
  $info['tokens']['dto']['dummy'] = [
    'name' => t('Just a dummy'),
  ];
  $info['tokens']['_eca_root_token']['dummy'] = [
    'name' => t('Just a dummy'),
  ];
  $info['tokens']['plain']['?'] = [
    'name' => '?',
    'description' => t('Use any available token. Example: <b>[plain:node:title]</b>'),
  ];
  return $info;
}

/**
 * Implements hook_token_info_alter().
 */
function eca_token_info_alter(array &$data): void {
  $definitions = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($definitions as $definition) {
    if ($definition instanceof ContentEntityTypeInterface && isset($data['tokens'][$definition->id()])) {
      if ($definition->hasKey('id') && !isset($data['tokens'][$definition->id()]['id'])) {
        $data['tokens'][$definition->id()]['id'] = [
          'name' => t('Entity ID'),
          'description' => t('The ID of the entity.'),
        ];
      }
      if ($definition->hasKey('label') && !isset($data['tokens'][$definition->id()]['label'])) {
        $data['tokens'][$definition->id()]['label'] = [
          'name' => t('Entity label'),
          'description' => t('The label of the entity.'),
        ];
      }
      $data['tokens'][$definition->id()]['entity_type'] = [
        'name' => t('Entity type'),
        'description' => t('The type ID of the entity.'),
      ];
      if ($definition->hasKey('bundle')) {
        $data['tokens'][$definition->id()]['bundle_id'] = [
          'name' => t('Entity bundle'),
          'description' => t('The bundle ID of the entity.'),
        ];
      }
    }
  }
}

/**
 * Implements hook_tokens().
 */
function eca_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];

  $definitions = \Drupal::entityTypeManager()->getDefinitions();
  if (isset($definitions[$type]) && !empty($data[$type]) && $data[$type] instanceof ContentEntityInterface) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          if ($definitions[$type]->hasKey('id')) {
            $replacements[$original] = $data[$type]->id();
          }
          break;

        case 'label':
          if ($definitions[$type]->hasKey('label')) {
            $replacements[$original] = $data[$type]->label();
          }
          break;

        case 'entity_type':
          $replacements[$original] = $data[$type]->getEntityTypeId();
          break;

        case 'bundle_id':
          $replacements[$original] = $data[$type]->bundle();
          break;

      }
    }
  }

  if ($type === 'dto' && !empty($data['dto'])) {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $data['dto'];
    foreach ($tokens as $name => $original) {
      $access_allowed = TRUE;
      $parts = explode(':', $name);
      $typed_object = $dto;
      $entity = NULL;
      $token_type = NULL;
      $value = NULL;

      // Traverse through the property path, possibly until the leaf point or
      // when we found a token type that may take over for token replacements.
      while (($typed_object instanceof TraversableTypedDataInterface) && !empty($parts)) {
        $property = array_shift($parts);
        if (method_exists($typed_object, 'get') && (isset($typed_object->$property) || ($typed_object instanceof \ArrayAccess && isset($typed_object[$property])))) {
          $typed_object = $typed_object->get($property);
        }
        elseif ($typed_object instanceof ListInterface && !ctype_digit((string) $property)) {
          // Allow implicitly jumping to the first item of the list.
          array_unshift($parts, $property);
          $typed_object = $typed_object->first();
        }
        else {
          $typed_object = NULL;
        }
        if (!($typed_object instanceof TypedDataInterface)) {
          $typed_object = NULL;
          break;
        }

        $value = $typed_object->getValue();

        // Perform access checks and add existing cacheability metadata.
        foreach ([$typed_object, $value] as $subject) {
          if ($subject instanceof CacheableDependencyInterface) {
            $bubbleable_metadata->addCacheableDependency($subject);
          }
          if ($subject instanceof AccessibleInterface) {
            $access_result = $subject->access('view', NULL, TRUE);
            $access_allowed = $access_allowed && $access_result->isAllowed();
            if ($access_result instanceof CacheableDependencyInterface) {
              $bubbleable_metadata->addCacheableDependency($access_result);
            }
          }
        }

        if ($value instanceof EntityInterface) {
          $entity = $value;
        }
        elseif (method_exists($typed_object, 'getEntity') && $entity = $typed_object->getEntity()) {
          // Field items and arbitrary item lists may belong to an entity.
          $bubbleable_metadata->addCacheableDependency($entity);
          $access_result = $entity->access('view', NULL, TRUE);
          $access_allowed = $access_allowed && $access_result->isAllowed();
          if ($access_result instanceof CacheableDependencyInterface) {
            $bubbleable_metadata->addCacheableDependency($access_result);
          }
        }

        if ($entity && $token_type = $token_services->getTokenType($entity)) {
          if (isset($entity->$property)) {
            array_unshift($parts, $property);
          }
          break;
        }

        if ($token_type = $token_services->getTokenType($value)) {
          if ((is_object($value) && isset($value->$property)) || ((is_array($value) || $value instanceof \ArrayAccess) && isset($value[$property]))) {
            array_unshift($parts, $property);
          }
          break;
        }
      }
      if (!$access_allowed || !$typed_object || !$value) {
        continue;
      }

      if ($token_type && $parts) {
        $chained_token = implode(':', $parts);
        if ($entity) {
          $replacements += \Drupal::token()->generate($token_type, [$chained_token => $original], [$token_type => $entity], $options, $bubbleable_metadata);
          if (isset($replacements[$original])) {
            continue;
          }
        }

        $replacements += \Drupal::token()->generate($token_type, [$chained_token => $original], [$token_type => $value], $options, $bubbleable_metadata);
        if (isset($replacements[$original])) {
          continue;
        }
      }

      if (empty($parts)) {
        // Within the scope of DTOs, we assume conscious handling of string
        // values by their users. Therefore we allow passing through the
        // replacement values as safe markup.
        // @todo Reconsider this once https://www.drupal.org/node/2580723
        // got fixed.
        $replacements[$original] = Markup::create($typed_object->getString());
      }
    }
  }

  if ($type === '_eca_root_token') {
    $available = $data + TokenServices::get()->getTokenData();
    foreach ($tokens as $name => $original) {
      if (isset($available[$name])) {
        $value = $available[$name] ?? NULL;
        $replacement = is_scalar($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString')) ? (string) $value : ($value instanceof EntityInterface ? $value->id() : '');
        if ($replacement !== '') {
          $replacements[$original] = Markup::create($replacement);
        }
      }
    }
  }

  if ($type === 'plain') {
    foreach ($tokens as $name => $original) {
      $replacement = (string) \Drupal::token()->replacePlain('[' . $name . ']', $data, ['clear' => TRUE] + $options, $bubbleable_metadata);
      if ($replacement !== '') {
        $replacements[$original] = Markup::create($replacement);
      }
    }
  }

  return $replacements;
}
