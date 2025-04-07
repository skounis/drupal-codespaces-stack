<?php

namespace Drupal\eca_base\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * ECA condition plugin for evaluating whether a token exists.
 *
 * @EcaCondition(
 *   id = "eca_token_exists",
 *   label = @Translation("Token: exists"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class TokenExists extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['token_name' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of the token to check for.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $token_name = trim((string) $this->configuration['token_name']);
    if ($token_name === '') {
      return FALSE;
    }
    $token_exists = NULL;

    // First, try a cautious but quick lookup into available data.
    $token_data = $this->tokenService->getTokenData($token_name);
    if (($token_data instanceof EntityInterface) && $this->tokenService->getTokenType($token_data)) {
      // When no brackets are given, the intention of the check is directly
      // targeted towards the entity itself, and in this case there is one.
      $token_exists = TRUE;
    }
    if (($token_data instanceof ComplexDataInterface || $token_data instanceof ListInterface) && $token_data->isEmpty()) {
      // Data is empty and thus it will not produce any output.
      $token_exists = FALSE;
    }
    elseif ($token_data instanceof DataTransferObject) {
      // We know how the DTO behaves on token resolution, and when not empty,
      // it will produce some output.
      $token_exists = TRUE;
    }

    if (NULL === $token_exists) {
      // Existence could not be resolved with the first try above, perform a
      // full resolution now.
      if (mb_substr($token_name, 0, 1) !== '[') {
        $token_name = '[' . $token_name . ']';
      }
      $token_exists = trim((string) $this->tokenService->replaceClear($token_name)) !== '';
    }

    return $this->negationCheck($token_exists);
  }

}
