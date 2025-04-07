<?php

namespace Drupal\ai_image_bulk_alt_text\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AltTextFixer extends FormBase {


  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_image_bulk_alt_text_fixer';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
    $instance->providerManager = $container->get('ai.provider');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->fileSystem = $container->get('file_system');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->database = $container->get('database');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $image_fields = $this->getEntitiesAndFields();

    // If there are no image fields, return a message.
    if (empty($image_fields)) {
      $form['message'] = [
        '#markup' => $this->t('<strong>You are good!</strong> No image fields found with missing alt text.'),
      ];
      return $form;
    }

    $header = [
      'image' => t('Image'),
      'entity_type' => t('Entity'),
      'suggested_alt_text' => t('New Alt Text'),
      'actions' => t('Actions'),
    ];

    $form['suggest'] = [
      '#type' => 'button',
      '#value' => $this->t('Bulk Generate Alt Text with AI'),
      '#attributes' => [
        'class' => ['suggest-alt-text'],
      ],
    ];


    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      '#input' => TRUE,
    ];

    $i = 0;
    foreach ($image_fields as $image) {
      // Load the image.
      // @var \Drupal\file\Entity\File $file */
      $form['table'][$i] = [
        'image' => [
            '#theme' => 'image_style',
            '#style_name' => 'large',
            '#uri' => $image['entity']->getFileUri(),
            '#alt' => $image['entity']->getFilename(),
        ],
        'entity_type' => [
            '#markup' => substr($image['base_entity']->label(), 0, 30) . ' (' . $image['field'] . ')',
        ],
        'suggested_alt_text' => [
            '#type' => 'textarea',
            '#default_value' => '',
            '#attributes' => [
              'class' => ['alt-text-' . $image['unique_id'], 'alt-text-textarea'],
            ],
            '#suffix' => '<div class="textarea-loader load-' . $image['unique_id'] . '"><div class="loader"></div>',
        ],
        'actions' => [
            '#type' => 'button',
            '#value' => $this->t('Generate with AI'),
            '#attributes' => [
              'class' => ['alt-text-item'],
              'data-unique-id' => $image['unique_id'],
              'data-entity-language' => $image['base_entity']->language()->getId() ?? 'en',
              'data-file-id' => $image['entity']->id(),
            ],
        ],
        'base_entity_id' => [
          '#type' => 'hidden',
          '#value' => $image['base_entity']->id(),
        ],
        'base_entity_type' => [
          '#type' => 'hidden',
          '#value' => $image['base_entity']->getEntityTypeId(),
        ],
        'field' => [
          '#type' => 'hidden',
          '#value' => $image['field'],
        ],
      ];
      $i++;
    }

    $form['#attached']['library'][] = 'ai_image_bulk_alt_text/suggest';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $descriptions = $form_state->getValue('table');
    $i = 0;
    foreach ($descriptions as $row) {
      // Meaning we want to save.
      if (!empty($row['suggested_alt_text'])) {
        $entity = $this->entityTypeManager->getStorage($row['base_entity_type'])->load($row['base_entity_id']);
        $entity->{$row['field']}->alt = $row['suggested_alt_text'];
        $entity->save();
        $i++;
      }
    }
    $this->messenger->addMessage($this->t('@count alt text fields have been updated.', ['@count' => $i]));
  }

  /**
   * Gets a list of all the entities and fields.
   *
   * @return array
   *   An array of entities and fields.
   */
  protected function getEntitiesAndFields($limit = 50) {
    $entity_types = $this->getImageFields();
    $missing_alt_text = [];
    $i = 0;
    foreach ($entity_types as $table => $field) {
      // Load all entities with this field.
      foreach ($this->entityTypeManager->getStorage($field['entity_type'])->loadMultiple() as $entity) {
        // Iterate over the field values.
        if (!empty($entity->{$field['field']})) {
          foreach ($entity->{$field['field']} as $item) {
            // Check if the alt text is empty.
            if (empty($entity->{$field['field']}->alt) && !empty($item->entity)) {
              $missing_alt_text[] = [
                'entity' => $item->entity,
                'field' => $field['field'],
                'type' => $field['entity_type'],
                'base_entity' => $entity,
                'bundle' => $field['bundle'],
                'item' => $item,
                'unique_id' => md5($entity->id() . '-' . $field['field'] . '-' . $item->target_id),
              ];
              $i++;
              if ($i == $limit) {
                return $missing_alt_text;
              }
            }
          }
        }
      }
    }
    return $missing_alt_text;
  }

  /**
   * Get the list of all the image fields for all entity types.
   *
   * @return array
   *   An array of image fields.
   */
  protected function getImageFields() {
    $fields = [];
    $entity_types = $this->getEntityTypes();
    foreach ($entity_types as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle_id => $bundle) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
          if ($field_definition->getType() == 'image') {
            // Check the config if alt text is enabled.
            $config = $this->config('field.field.' . $entity_type_id . '.' . $bundle_id . '.' . $field_name);
            if (!$config->get('settings.alt_field')) {
              continue;
            }
            // Get the table name for this field.
            $table = $entity_type_id . '__' . $field_name;
            $fields[$table] = [
              'entity_type' => $entity_type_id,
              'bundle' => $bundle_id,
              'field' => $field_name,
            ];
          }
        }
      }
    }
    return $fields;
  }

  /**
   * Get a list of all content entities and bundles.
   *
   * @return array
   *   An array of entity types and bundles.
   */
  protected function getEntityTypes() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $bundles = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Check if its a content entity type.
      if (!$entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
        continue;
      }
      $bundles[$entity_type_id] = $this->bundleInfo->getBundleInfo($entity_type_id);
    }
    return $bundles;
  }
}
