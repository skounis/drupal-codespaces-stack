<?php

namespace Drupal\eca_content\Plugin;

use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Trait for comparing entities within ECA action and condition plugins.
 */
trait EntityDiffTrait {

  /**
   * Default configuration used by all plugin that use this trait.
   *
   * @return array
   *   The default configuration.
   */
  public function commonDefaultConfiguration(): array {
    return [
      'token_name' => '',
      'compare_token_name' => '',
      'exclude_fields' => [],
      'include_fields' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Helper function to build the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The build form.
   */
  public function doBuildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a token that holds the result list.'),
      '#default_value' => $this->configuration['token_name'],
      '#required' => TRUE,
      '#weight' => -60,
      '#eca_token_reference' => TRUE,
    ];
    $form['compare_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('compare_token_name'),
      '#description' => $this->t('Provide the name of a token that holds the original entity.'),
      '#default_value' => $this->configuration['compare_token_name'],
      '#required' => TRUE,
      '#weight' => 30,
      '#eca_token_reference' => TRUE,
    ];
    $form['exclude_fields'] = [
      '#type' => 'textarea',
      '#title' => 'Exclude fields',
      '#description' => $this->t('List machine names of fields that should be ignored. Provide 1 name per line.'),
      '#default_value' => implode("\n", $this->configuration['exclude_fields']),
      '#weight' => 40,
    ];
    $form['include_fields'] = [
      '#type' => 'textarea',
      '#title' => 'Include fields',
      '#description' => $this->t('List machine names of fields that should be considered for comparison. Provide 1 name per line. Leave this field empty to include all fields, except for the excluded fields.'),
      '#default_value' => implode("\n", $this->configuration['include_fields']),
      '#weight' => 40,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Helper function to submit the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function doSubmitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['compare_token_name'] = $form_state->getValue('compare_token_name');
    $this->configuration['exclude_fields'] = explode("\n", $form_state->getValue('exclude_fields', ''));
    $this->configuration['include_fields'] = explode("\n", $form_state->getValue('include_fields', ''));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Help function to check access for ECA action and condition plugin.
   *
   * @param mixed $object
   *   The object to execute the action on.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user for which to check access, or NULL to check access
   *   for the current user. Defaults to NULL.
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result. Returns a boolean if $return_as_object is FALSE (this
   *   is the default) and otherwise an AccessResultInterface object.
   *   When a boolean is returned, the result of AccessInterface::isAllowed() is
   *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
   *   access is either explicitly forbidden or "no opinion".
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    if ($object instanceof ContentEntityInterface &&
      $this->tokenService->hasTokenData($this->configuration['compare_token_name']) &&
      ($compareEntity = $this->tokenService->getTokenData($this->configuration['compare_token_name'])) &&
      $compareEntity instanceof ContentEntityInterface &&
      $object->getEntityTypeId() === $compareEntity->getEntityTypeId() &&
      $object->bundle() === $compareEntity->bundle()
    ) {
      $result = $object->access('view', $account, TRUE)->andIf($compareEntity->access('view', $account, TRUE));
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Helper function to compare 2 entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to compare with the configured entity.
   *
   * @return array
   *   The list of changed fields and their values.
   */
  public function compare(ContentEntityInterface $entity): array {
    $compareEntity = $this->tokenService->getTokenData($this->configuration['compare_token_name']);
    if ($entity instanceof WebformSubmissionInterface) {
      $diff = DiffArray::diffAssocRecursive($entity->toArray(TRUE), $compareEntity->toArray(TRUE));
    }
    else {
      $diff = DiffArray::diffAssocRecursive($entity->toArray(), $compareEntity->toArray());
    }
    $exclude_fields = $this->configuration['exclude_fields'];
    if (count($exclude_fields)) {
      foreach ($exclude_fields as $field_name) {
        unset($diff[$field_name]);
      }
    }
    $include_fields = $this->configuration['include_fields'];
    if (count($include_fields)) {
      $included = [];
      foreach ($include_fields as $field_name) {
        if (isset($diff[$field_name])) {
          $included[$field_name] = $diff[$field_name];
        }
      }
      $diff = $included;
    }
    return $diff;
  }

}
