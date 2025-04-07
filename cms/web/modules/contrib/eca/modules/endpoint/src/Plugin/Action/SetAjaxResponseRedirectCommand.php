<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Add a redirect command to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_redirect",
 *   label = @Translation("Ajax Response: redirect"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseRedirectCommand extends ResponseAjaxCommandBase {

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
    $url = (string) $this->tokenService->replaceClear($this->configuration['url']);
    return new RedirectCommand($url);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The URL that will be loaded into window.location. This should be a full URL.'),
      '#default_value' => $this->configuration['url'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['url'] = (string) $form_state->getValue('url');
    parent::submitConfigurationForm($form, $form_state);
  }

}
