<?php

namespace Drupal\eca_form\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\ContentEntityEventInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Dispatched when an inline entity form is being build.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_form\Event
 */
class InlineEntityFormBuild extends FormBuild implements ContentEntityEventInterface {

  /**
   * The embedded entity that belongs to the inline entity form.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * The parent entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $parent;

  /**
   * The name of the field (of the parent) that renders the inline entity form.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * The delta of the entity reference in the field.
   *
   * @var int
   */
  protected int $delta;

  /**
   * The widget plugin ID used to render the inline entity form.
   *
   * @var string
   */
  protected string $widgetPluginId;

  /**
   * An instance holding event data accessible as Token.
   *
   * @var \Drupal\eca\Plugin\DataType\DataTransferObject|null
   */
  protected ?DataTransferObject $eventData = NULL;

  /**
   * Constructs an InlineEntityFormBuild instance.
   *
   * @param array &$subform
   *   The render array of the inline entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent form state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   The parent.
   * @param string $field_name
   *   The field name of the parent.
   * @param int $delta
   *   The delta within the field of the parent.
   * @param string $widget_plugin_id
   *   The plugin ID of the field widget.
   */
  public function __construct(array &$subform, FormStateInterface $form_state, ContentEntityInterface $entity, ContentEntityInterface $parent, string $field_name, int $delta, string $widget_plugin_id) {
    parent::__construct($subform, $form_state);
    $this->entity = $entity;
    $this->parent = $parent;
    $this->fieldName = $field_name;
    $this->delta = $delta;
    $this->widgetPluginId = $widget_plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Get the parent entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The parent entity.
   */
  public function getParent(): ContentEntityInterface {
    return $this->parent;
  }

  /**
   * Get field name of the parent that renders the inline entity form.
   *
   * @return string
   *   The field name.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

  /**
   * Get the delta of the entity reference in the field.
   *
   * @return int
   *   The delta.
   */
  public function getDelta(): int {
    return $this->delta;
  }

  /**
   * Get the widget plugin ID.
   *
   * @return string
   *   The widget plugin ID.
   */
  public function getWidgetPluginId(): string {
    return $this->widgetPluginId;
  }

}
