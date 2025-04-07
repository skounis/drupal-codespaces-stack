<?php

namespace Drupal\eca_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Action to set an access result.
 *
 * @Action(
 *   id = "eca_access_set_result",
 *   label = @Translation("Set access result"),
 *   description = @Translation("Only works when reacting upon <em>ECA Access</em> events."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class SetAccessResult extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->event instanceof AccessEventInterface ? AccessResult::allowed() : AccessResult::forbidden("Event is not compatible with this action.");
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!($this->event instanceof AccessEventInterface)) {
      return;
    }
    $accessResult = $this->configuration['access_result'];
    if ($accessResult === '_eca_token') {
      $accessResult = $this->getTokenValue('access_result', 'forbidden');
    }

    switch ($accessResult) {

      case 'allowed':
        // Allowed by configured ECA.
        $result = AccessResult::allowed();
        break;

      case 'neutral':
        $result = AccessResult::neutral("Neutral by configured ECA");
        break;

      default:
        $result = AccessResult::forbidden("Forbidden by configured ECA");

    }

    $this->event->setAccessResult($result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'access_result' => 'forbidden',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['access_result'] = [
      '#type' => 'select',
      '#title' => $this->t('Access result'),
      '#description' => $this->t('Please note: This action only works when reacting upon <em>access</em> events.'),
      '#default_value' => $this->configuration['access_result'],
      '#options' => [
        'forbidden' => $this->t('Forbidden'),
        'neutral' => $this->t('Neutral (no opinion)'),
        'allowed' => $this->t('Allowed'),
      ],
      '#weight' => 10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['access_result'] = $form_state->getValue('access_result');
    parent::submitConfigurationForm($form, $form_state);
  }

}
