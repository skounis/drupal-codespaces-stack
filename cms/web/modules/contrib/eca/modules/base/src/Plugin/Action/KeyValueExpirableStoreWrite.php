<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Action to write value to the expirable key value store.
 *
 * @Action(
 *   id = "eca_keyvalueexpirablestore_write",
 *   label = @Translation("Expirable key value store: write"),
 *   description = @Translation("Writes a value to the Drupal expirable key value store by the given key."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class KeyValueExpirableStoreWrite extends KeyValueExpirableStoreRead {

  /**
   * {@inheritdoc}
   */
  protected function writeMode(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doStore(string $collection, bool $ifNotExists, string $key, mixed $value): void {
    if ($ifNotExists) {
      $this->store($collection)->setWithExpireIfNotExists($key, $value, $this->configuration['expires']);
    }
    else {
      $this->store($collection)->setWithExpire($key, $value, $this->configuration['expires']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['expires' => 60] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['expires'] = [
      '#type' => 'number',
      '#title' => $this->t('Expiration'),
      '#default_value' => $this->configuration['expires'],
      '#description' => $this->t('The time to live for this item, in seconds.'),
      '#weight' => -50,
      '#min' => 1,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['expires'] = $form_state->getValue('expires');
    parent::submitConfigurationForm($form, $form_state);
  }

}
