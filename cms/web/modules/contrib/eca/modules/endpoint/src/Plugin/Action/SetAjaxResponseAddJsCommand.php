<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AddJsCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Add JS to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_add_js",
 *   label = @Translation("Ajax Response: add js"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAddJsCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $url = (string) $this->tokenService->replaceClear($this->configuration['url']);
    if (!is_string($url) || $url === '') {
      $result = AccessResult::forbidden();
    }
    else {
      $result = parent::access($object, $account, TRUE);
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAjaxCommand(): CommandInterface {
    $script = [
      'src' => (string) $this->tokenService->replaceClear($this->configuration['url']),
      'defer' => (bool) $this->configuration['defer'],
    ];
    $selector = (string) $this->tokenService->replaceClear($this->configuration['selector']);
    if ($selector === '') {
      $selector = 'body';
    }
    return new AddJsCommand($script, $selector);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
      'defer' => TRUE,
      'selector' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The src attribute for the script.'),
      '#default_value' => $this->configuration['url'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['defer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear previous messages'),
      '#description' => $this->t('If TRUE, loading of the script will be deferred.'),
      '#default_value' => $this->configuration['defer'],
      '#weight' => -40,
    ];
    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Selector'),
      '#description' => $this->t('A CSS selector of the element where the script tags will be appended.'),
      '#default_value' => $this->configuration['selector'],
      '#weight' => -35,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['url'] = (string) $form_state->getValue('url');
    $this->configuration['defer'] = (string) $form_state->getValue('defer');
    $this->configuration['selector'] = (string) $form_state->getValue('selector');
    parent::submitConfigurationForm($form, $form_state);
  }

}
