<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set available options on a field.
 *
 * @Action(
 *   id = "eca_form_field_set_options",
 *   label = @Translation("Form field: set options"),
 *   description = @Translation("Defines available options on an existing multi-value selection, radio or checkbox field."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormFieldSetOptions extends FormFieldActionBase {

  use FormFieldSetOptionsTrait {
    execute as doExecute;
  }

  /**
   * Whether to use form field value filters or not.
   *
   * @var bool
   */
  protected bool $useFilters = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Directly call the parent of the parent, to save a bit of redundant
    // access check overhead.
    $result = FormActionBase::access($object, $account, TRUE);

    if ($result->isAllowed()) {
      $original_field_name = $this->configuration['field_name'];

      $missing_form_fields = [];
      foreach ($this->extractFormFieldNames($original_field_name) as $field_name) {
        $this->configuration['field_name'] = $field_name;
        if ($element = &$this->getTargetElement()) {
          $element = &$this->jumpToFirstFieldChild($element);
        }
        if (!$element || !isset($element['#options'])) {
          $missing_form_fields[] = $field_name;
        }
      }

      if ($missing_form_fields) {
        $form_field_result = AccessResult::forbidden(sprintf("The following form fields were not found, or they are no valid option fields: %s", implode(', ', $missing_form_fields)));
      }
      else {
        $form_field_result = AccessResult::allowed();
      }

      $result = $result->andIf($form_field_result);

      // Restoring the original config entry.
      $this->configuration['field_name'] = $original_field_name;
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  // @codingStandardsIgnoreStart
  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // It is necessary to define the method here, otherwise the method of
    // the trait would be used instead.
    parent::execute();
  }
  // @codingStandardsIgnoreEnd

}
