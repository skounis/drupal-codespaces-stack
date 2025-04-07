<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ListOperationBase;

/**
 * Action to compare items in two lists.
 *
 * @Action(
 *   id = "eca_list_compare",
 *   label = @Translation("List: compare items"),
 *   description = @Translation("Compares the items in two simple lists (contained in tokens), returning the array of results."),
 * )
 */
class ListCompare extends ListOperationBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $result = [];
    $method = $this->configuration['method'];
    switch ($method) {
      case 'array_diff':
        $result = $this->getDiff($this->configuration['list_token'], $this->configuration['secondary_list_token']);
        break;

      case 'array_intersect':
        $result = $this->getIntersect($this->configuration['list_token'], $this->configuration['secondary_list_token']);
        break;

    }
    if (!empty($result)) {
      $this->tokenService->addTokenData($this->configuration['result_token_name'], $result);
    }
  }

  /**
   * Receives a token and counts the contained items.
   *
   * @param string $name
   *   Name of token object which contains the primary list to compare.
   * @param string $name2
   *   Name of token object which contains the secondary list to compare.
   *
   * @return array
   *   Result of the array_diff
   */
  protected function getDiff(string $name, string $name2): array {
    $result = [];
    if ($this->tokenService->hasTokenData($name) && $this->tokenService->hasTokenData($name2)) {
      $array1 = $this->tokenService->getTokenData($name)->toArray();
      $array2 = $this->tokenService->getTokenData($name2)->toArray();
      $result = array_diff($array1, $array2);
    }
    return $result;
  }

  /**
   * Receives a token and counts the contained items.
   *
   * @param string $name
   *   Name of token object which contains the primary list to compare.
   * @param string $name2
   *   Name of token object which contains the secondary list to compare.
   *
   * @return array
   *   Result of the array_intersect
   */
  protected function getIntersect(string $name, string $name2): array {
    $result = [];
    if ($this->tokenService->hasTokenData($name) && $this->tokenService->hasTokenData($name2)) {
      $array1 = $this->tokenService->getTokenData($name)->toArray();
      $array2 = $this->tokenService->getTokenData($name2)->toArray();
      $result = array_intersect($array1, $array2);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'secondary_list_token' => '',
      'method' => 'array_diff',
      'result_token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['secondary_list_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token containing the secondary list'),
      '#description' => $this->t('Provide the name of the token that contains the secondary list in the comparison.'),
      '#default_value' => $this->configuration['secondary_list_token'],
      '#required' => TRUE,
      '#weight' => 5,
      '#eca_token_reference' => TRUE,
    ];
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Array Function'),
      '#description' => $this->t('Returns an array of items found by the <a href="https://www.php.net/manual/en/ref.array.php">Array Function</a> selected.'),
      '#default_value' => $this->configuration['method'],
      '#required' => TRUE,
      '#weight' => 10,
      '#options' => [
        'array_diff' => $this->t('Find differences (array_diff)'),
        'array_intersect' => $this->t('Find common items (array_intersect)'),
      ],
    ];
    $form['result_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of result token'),
      '#description' => $this->t('Provide the name of a new token where the resulting array should be stored.'),
      '#default_value' => $this->configuration['result_token_name'],
      '#required' => TRUE,
      '#weight' => 20,
      '#eca_token_reference' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['list_token']['#title'] = $this->t('Token containing the primary list');
    $form['list_token']['#required'] = TRUE;
    $form['list_token']['#description'] = $this->t('Provide the name of the token that contains the primary list in the comparison.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['secondary_list_token'] = $form_state->getValue('secondary_list_token');
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['result_token_name'] = $form_state->getValue('result_token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
