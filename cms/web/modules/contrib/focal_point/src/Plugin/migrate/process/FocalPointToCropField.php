<?php

namespace Drupal\focal_point\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Calculates crop_field_data values.
 *
 * @MigrateProcessPlugin(
 *   id = "focal_point_to_crop",
 *   handle_multiples = TRUE
 * )
 */
class FocalPointToCropField extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ImageFactory $image_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $crop_field_data = [];
    $file_storage = $this->entityTypeManager->getStorage('file');
    $file = $file_storage->load($value[0]);
    if (is_null($file)) {
      throw new MigrateSkipRowException($this->t('File not found.'));
    }
    $image = $this->imageFactory->get($file->getFileUri());
    $focal_point = explode(',', $value[1]);
    $crop_field_data['uri'] = $file->getFileUri();
    $crop_field_data['x'] = (int) round((($focal_point[0]) / 100) * $image->getWidth());
    $crop_field_data['y'] = (int) round((($focal_point[1]) / 100) * $image->getHeight());
    return $crop_field_data;
  }

}
