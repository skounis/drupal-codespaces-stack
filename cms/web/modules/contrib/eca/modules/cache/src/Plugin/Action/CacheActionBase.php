<?php

namespace Drupal\eca_cache\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Base class for config-related actions.
 */
abstract class CacheActionBase extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->getCacheBackend() && $this->getCacheKey() ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'backend' => 'eca_default',
      'key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['backend'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache backend'),
      '#options' => $this->getBackendOptions(),
      '#default_value' => $this->configuration['backend'] ?? '',
      '#required' => TRUE,
      '#weight' => -60,
      '#eca_token_select_option' => TRUE,
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cache key'),
      '#description' => $this->t('The cache key is a unique machine name and identifies the cache item.'),
      '#default_value' => $this->configuration['key'] ?? '',
      '#required' => TRUE,
      '#weight' => -50,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['backend'] = $form_state->getValue('backend');
    $this->configuration['key'] = $form_state->getValue('key');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Get the cache backend used by this plugin.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface|null
   *   The cache backend, or NULL if not configured or does not exist.
   */
  protected function getCacheBackend(): ?CacheBackendInterface {
    $backend = $this->configuration['backend'];
    if ($backend === '_eca_token') {
      $backend = $this->getTokenValue('backend', 'eca_default');
    }
    if (!empty($backend)) {
      $service_name = 'cache.' . $this->configuration['backend'];
      // @phpstan-ignore-next-line
      if (\Drupal::hasService($service_name)) {
        // @phpstan-ignore-next-line
        return \Drupal::service($service_name);
      }
    }
    return NULL;
  }

  /**
   * Get the available cache backend options.
   *
   * @return array
   *   The backend options.
   */
  protected function getBackendOptions(): array {
    $backends = [];
    foreach (Cache::getBins() as $bin => $service) {
      $backends[$bin] = $bin;
    }
    return $backends;
  }

  /**
   * Get the cache key.
   *
   * @return string|null
   *   The cache key, or NULL if not defined.
   */
  protected function getCacheKey(): ?string {
    $key = trim($this->tokenService->replaceClear($this->configuration['key'] ?? ''));
    return $key === '' ? NULL : $key;
  }

  /**
   * Get the cache tags using the "tags" configuration key.
   *
   * @return array
   *   The cache tags.
   */
  protected function getCacheTags(): array {
    $tags = [];
    if ($this->configuration['tags'] !== '') {
      $tags = array_values(DataTransferObject::buildArrayFromUserInput($this->configuration['tags']));
    }
    return $tags;
  }

}
