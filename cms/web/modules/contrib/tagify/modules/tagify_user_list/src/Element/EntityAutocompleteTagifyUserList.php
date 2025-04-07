<?php

namespace Drupal\tagify_user_list\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;

/**
 * Provides an entity autocomplete tagify user list form element.
 *
 * The autocomplete tagify user list form element allows users to select user
 * entity, which can come from specific bundles of user entity type.
 *
 * Properties:
 * - #target_type: (required) The ID of the target entity type.
 * - #tags: (optional) TRUE if the element allows multiple selection. Defaults
 *   to FALSE.
 * - #default_value: (optional) The default entity or an array of default
 *   entities, depending on the value of #tags.
 * - #selection_handler: (optional) The plugin ID of the entity reference
 *   selection handler (a plugin of type EntityReferenceSelection). The default
 *   value is the lowest-weighted plugin that is compatible with #target_type.
 * - #selection_settings: (optional) An array of settings for the selection
 *   handler. Settings for the default selection handler
 *   \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection are:
 *   - target_bundles: Array of bundles to allow (omit to allow all bundles).
 *   - sort: Array with 'field' and 'direction' keys, determining how results
 *     will be sorted. Defaults to unsorted.
 * - #autocreate: (optional) Array of settings used to auto-create entities
 *   that do not exist (omit to not auto-create entities). Elements:
 *   - bundle: (required) Bundle to use for auto-created entities.
 *   - uid: User ID to use as the author of auto-created entities. Defaults to
 *     the current user.
 *  - #max_items: (optional) The maximum number of items that will be shown.
 *  - #suggestions_dropdown: (required) The method uses to show the suggestions
 *     dropdown.
 *  - #match_operator: (required) The autocomplete matching option.
 *
 * Usage example:
 * @code
 * $form['my_element'] = [
 *  '#type' => 'entity_autocomplete_tagify_user_list',
 *  '#target_type' => 'node',
 *  '#tags' => TRUE,
 *  '#default_value' => [['value' => 'entity label', 'entity_id' => 1]],
 *  '#selection_handler' => 'default',
 *  '#selection_settings' => [
 *    'target_bundles' => ['article', 'page'],
 *   ],
 *  '#autocreate' => [
 *    'bundle' => 'article',
 *    'uid' => <a valid user ID>,
 *   ],
 *  '#max_items' => 10,
 *  '#suggestions_dropdown' => 1,
 *  '#max_operator' => 'CONTAINS',
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection
 *
 * @FormElement("entity_autocomplete_tagify_user_list")
 */
class EntityAutocompleteTagifyUserList extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    $info = parent::getInfo();

    $info['#maxlength'] = NULL;
    $info['#autocreate'] = NULL;
    $info['#cardinality'] = -1;
    $info['#max_items'] = 10;
    $info['#suggestions_dropdown'] = 1;
    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'default';
    $info['#selection_settings_key'] = [];
    $info['#match_operator'] = 'CONTAINS';
    $info['#info_label'] = '';
    $info['#identifier'] = '';
    array_unshift(
      $info['#process'],
      [$class, 'processEntityAutocompleteTagifyUserList']
    );

    return $info;
  }

  /**
   * Adds entity autocomplete tagify user list functionality to a form element.
   *
   * @param array $element
   *   The form element to process. Properties used:
   *   - #target_type: The ID of the target entity type.
   *   - #selection_handler: The plugin ID of the entity reference selection
   *     handler.
   *   - #selection_settings: An array of settings that will be passed to the
   *     selection handler.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function processEntityAutocompleteTagifyUserList(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Nothing to do if there is no target entity type.
    if (empty($element['#target_type'])) {
      throw new \InvalidArgumentException('Missing required #target_type parameter.');
    }

    if ($element['#autocreate']) {
      $element['#attributes']['class'][] = 'autocreate';
    }

    $element['#attached'] = [
      'library' => [
        'tagify/tagify',
        'tagify/default',
        'tagify/tagify_polyfils',
        'tagify_user_list/user_list',
      ],
    ];

    if (_tagify_is_gin_theme_active()) {
      $element['#attached']['library'][] = 'tagify/gin';
    }

    if (_tagify_is_claro_theme_active()) {
      $element['#attached']['library'][] = 'tagify/claro';
    }

    if (!isset($element['#attributes']['class'])) {
      $element['#attributes']['class'] = [];
    }
    $element['#attributes']['class'][] = 'tagify-user-list-widget';

    // Find the index of 'tagify-widget' class in the array.
    $index = array_search('tagify-widget', $element['#attributes']['class'], FALSE);
    // Remove the 'tagify-widget' class if it exists.
    if ($index !== FALSE) {
      unset($element['#attributes']['class'][$index]);
    }
    // Re-index the array after removing the class.
    $element['#attributes']['class'] = array_values($element['#attributes']['class']);

    // Add Tagify attributes.
    if ($element['#max_items']) {
      $element['#attributes']['data-max-items'] = $element['#max_items'];
    }
    $element['#attributes']['data-suggestions-dropdown'] = $element['#suggestions_dropdown'] ?? '';
    $element['#attributes']['data-match-operator'] = ($element['#match_operator'] === 'CONTAINS') ? 1 : 0;
    $element['#attributes']['data-placeholder'] = $element['#placeholder'] ?? '';
    $element['#attributes']['data-identifier'] = array_key_exists('#field_name', $element) ? $element['#field_name'] : $element['#name'];
    $element['#attributes']['data-cardinality'] = $element['#cardinality'] ?? '';

    // Store the selection settings in the key/value store and pass a hashed key
    // in the route parameters.
    $selection_settings = $element['#selection_settings'] ?? [];
    $data = serialize($selection_settings) . $element['#target_type'] . $element['#selection_handler'];
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $element['#attributes']['data-autocomplete-url'] = Url::fromRoute('tagify_user_list.entity_autocomplete', [
      'target_type' => $element['#target_type'],
      'selection_handler' => $element['#selection_handler'],
      'selection_settings_key' => $selection_settings_key,
    ])->toString();

    // Information text.
    $element['#attached']['drupalSettings']['tagify']['information_message'] = [
      'limit_tag' => t('Tags are limited to:'),
      'no_matching_suggestions' => t('No matching suggestions found for:'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#default_value']) {
      // Extract the labels from the passed-in entity objects, taking access
      // checks into account.
      return static::getTagifyDefaultValue($element['#default_value'], $element['#info_label'], $element['#image_style']);
    }

    // Potentially the #value is set directly, so it contains the 'target_id'
    // array structure instead of a string.
    if (FALSE !== $input && is_array($input)) {
      $entity_ids = array_map(static function (array $item) {
        return $item['target_id'];
      }, $input);
      $entities = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($entity_ids);

      return static::getTagifyDefaultValue($entities, $element['#info_label'], $element['#image_style']);
    }

    return NULL;
  }

  /**
   * Formats the default values array for the tagify widget.
   *
   * This method is also responsible for checking the 'view label'
   * access on the passed-in entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entity objects.
   * @param string $info_label_template
   *   The extra information to be shown below the entity label.
   * @param string $image_style
   *   The image style to be used.
   *
   * @return false|string
   *   The tagify default values array. Associative array with at least the
   *   following keys:
   *   - 'value':
   *     The text to be shown in the autocomplete and tagify, IE: "My label"
   *   - 'entity_id':
   *     The referenced entity ID.
   *   - 'info_label':
   *     The extra information to be shown below the entity label.
   *   - 'avatar':
   *     The user image.
   */
  public static function getTagifyDefaultValue(array $entities, string $info_label_template, string $image_style) {
    $default_value = [];
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');
    foreach ($entities as $entity) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      // Set the entity in the correct language for display.
      $entity = $entity_repository->getTranslationFromContext($entity);
      if ($entity->getEntityTypeId() !== 'user') {
        return FALSE;
      }
      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      $label = ($entity->access('view label'))
        ? $entity->label()
        : t('- Restricted access -');
      // Get image path.
      $image_url = '';
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      if ($entity->hasField('user_picture')
        && !$entity->get('user_picture')->isEmpty()
      ) {
        /** @var \Drupal\file\FileInterface $user_image */
        $user_image = $entity->get('user_picture')->entity;
        /** @var \Drupal\image\Entity\ImageStyle $style */
        $style = ImageStyle::load($image_style);
        $image_url = $style->buildUrl($user_image->getFileUri());
      }
      $tagify_user_list_path = \Drupal::service('extension.list.module')
        ->getPath('tagify_user_list');

      $info_label = \Drupal::token()->replacePlain(
        $info_label_template,
        [
          $entity->getEntityTypeId() => $entity,
        ],
        [
          'clear' => TRUE,
        ]
      );
      $info_label = trim(preg_replace('/\s+/', ' ', $info_label));

      $context = ['entity' => $entity, 'info_label' => $info_label_template];
      \Drupal::moduleHandler()->alter('tagify_autocomplete_match', $label, $info_label, $context);

      if ($label === NULL) {
        continue;
      }

      $default_value[] = [
        'value' => $entity->id(),
        'entity_id' => $entity->id(),
        'label' => $label,
        'info_label' => $info_label,
        'avatar' => $image_url ?: '/' . $tagify_user_list_path . '/images/no-user.svg',
        'editable' => FALSE,
      ];
    }

    return json_encode($default_value);
  }

}
