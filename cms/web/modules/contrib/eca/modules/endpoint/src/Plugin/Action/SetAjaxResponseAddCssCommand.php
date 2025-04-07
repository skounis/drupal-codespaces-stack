<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AddCssCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Add CSS to the ajax response.
 *
 * @Action(
 *   id = "eca_endpoint_set_ajax_response_add_css",
 *   label = @Translation("Ajax Response: add css"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class SetAjaxResponseAddCssCommand extends ResponseAjaxCommandBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $href = (string) $this->tokenService->replaceClear($this->configuration['href']);
    if (!is_string($href) || $href === '') {
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
    $style = [
      'href' => (string) $this->tokenService->replaceClear($this->configuration['href']),
    ];
    $media = (string) $this->tokenService->replaceClear($this->configuration['media']);
    if ($media !== '') {
      $style['media'] = $media;
    }
    return new AddCssCommand([$style]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'href' => '',
      'media' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['href'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS file'),
      '#description' => $this->t('The href attribute for the styles, e.g. "/my_styles/special.css".'),
      '#default_value' => $this->configuration['href'],
      '#weight' => -45,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['media'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Media'),
      '#description' => $this->t('This attribute defines which media the style should be applied to.'),
      '#default_value' => $this->configuration['media'],
      '#weight' => -40,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['href'] = (string) $form_state->getValue('href');
    $this->configuration['media'] = (string) $form_state->getValue('media');
    parent::submitConfigurationForm($form, $form_state);
  }

}
