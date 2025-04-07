<?php

namespace Drupal\scheduler_content_moderation_integration\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'scheduler moderation' widget.
 *
 * @FieldWidget(
 *   id = "scheduler_moderation",
 *   label = @Translation("Scheduler Moderation"),
 *   description = @Translation("Select list for choosing a state. Defined by Scheduler Content Moderation Integration module."),
 *   field_types = {
 *     "list_string",
 *   }
 * )
 */
class SchedulerModerationWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected ModerationInformationInterface $moderationInformation,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('content_moderation.moderation_information'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // @todo Can this conditional ever be false? There is no test coverage for
    // that situation (if it exists).
    if ($form_state->getFormObject() instanceof ContentEntityForm) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $form_state->getFormObject()->getEntity();
      if (!$this->moderationInformation->isModeratedEntity($entity)) {
        $element['#access'] = FALSE;
      }
    }

    $scheduler_manager = \Drupal::service('scheduler.manager');
    if (!empty($this->entity)) {
      $scheduling_permission_name = $scheduler_manager->permissionName($this->entity->getEntityTypeId(), 'schedule');
      // If the user is not allowed to set the publishing or un-publishing dates
      // or there are no options to select, then hide the element.
      if (!\Drupal::currentUser()->hasPermission($scheduling_permission_name) || !$this->getOptions($items->getEntity())) {
        $element['#access'] = FALSE;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state): void {
    if (is_array($element['#value'])) {
      $value = current($element['#value']);
    }
    else {
      $value = $element['#value'];
    }
    $form_state->setValueForElement($element, [
      $element['#key_column'] => $value,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition->getFieldStorageDefinition()->getProvider() === 'scheduler_content_moderation_integration') {
      return TRUE;
    }
    return FALSE;
  }

}
