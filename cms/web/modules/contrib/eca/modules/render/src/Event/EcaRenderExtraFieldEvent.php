<?php

namespace Drupal\eca_render\Event;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\eca\Event\EntityApplianceTrait;
use Drupal\eca\Event\EntityEventInterface;

/**
 * Dispatched when an extra field is being rendered via ECA.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderExtraFieldEvent extends EcaRenderEventBase implements EntityEventInterface {

  use EntityApplianceTrait;

  /**
   * The entity in scope.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The machine name of the extra field.
   *
   * @var string
   */
  protected string $extraFieldName;

  /**
   * The options of the display component.
   *
   * @var array
   */
  protected array $options;

  /**
   * The render array build.
   *
   * @var array
   */
  protected array $build;

  /**
   * The entity display.
   *
   * @var \Drupal\Core\Entity\Display\EntityDisplayInterface
   */
  protected EntityDisplayInterface $display;

  /**
   * The display mode.
   *
   * @var string
   */
  protected string $viewMode;

  /**
   * The display type, either one of "display" or "form".
   *
   * @var string
   */
  protected string $displayType;

  /**
   * Constructs a new EcaRenderExtraFieldEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity in scope.
   * @param string $extra_field_name
   *   The machine name of the extra field.
   * @param array $options
   *   The options of the display component.
   * @param array &$build
   *   The render array build.
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The entity display.
   * @param string $view_mode
   *   The display mode.
   * @param string $display_type
   *   The display type, either one of "display" or "form".
   */
  public function __construct(EntityInterface $entity, string $extra_field_name, array $options, array &$build, EntityDisplayInterface $display, string $view_mode, string $display_type) {
    $this->entity = $entity;
    $this->extraFieldName = $extra_field_name;
    $this->options = $options;
    $this->build = &$build;
    $this->display = $display;
    $this->viewMode = $view_mode;
    $this->displayType = $display_type;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    return $this->build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Returns the display type.
   *
   * @return string
   *   The display type.
   */
  public function getDisplayType(): string {
    return $this->displayType;
  }

  /**
   * Returns the extra field name.
   *
   * @return string
   *   The extra field name.
   */
  public function getExtraFieldName(): string {
    return $this->extraFieldName;
  }

  /**
   * Get the options array.
   *
   * @return array
   *   The options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Get the entity display.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The entity display.
   */
  public function getDisplay(): EntityDisplayInterface {
    return $this->display;
  }

  /**
   * Get the view mode.
   *
   * @return string
   *   The view mode.
   */
  public function getViewMode(): string {
    return $this->viewMode;
  }

}
