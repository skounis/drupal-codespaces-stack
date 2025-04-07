<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_misc\Plugin\RouteInterface;
use Drupal\eca_misc\Plugin\RouteTrait;

/**
 * Loads a route parameter into the token environment.
 *
 * @Action(
 *   id = "eca_token_load_route_param",
 *   label = @Translation("Token: load route parameter"),
 *   description = @Translation("Loads a route parameter into the token environment."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class TokenLoadRouteParameter extends ConfigurableActionBase {

  use RouteTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $allowed = FALSE;
    $parameter_name = $this->tokenService->replace($this->configuration['parameter_name']);
    if ($parameter = $this->getRouteMatch()->getParameter($parameter_name)) {
      $allowed = TRUE;
      if ($parameter instanceof AccessibleInterface) {
        $allowed = $parameter->access('view', $account);
      }
    }
    $result = AccessResult::allowedIf($allowed);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $parameter_name = $this->tokenService->replace($this->configuration['parameter_name']);
    if ($parameter = $this->getRouteMatch()->getParameter($parameter_name)) {
      $tokenName = empty($this->configuration['token_name']) ? $this->tokenService->getTokenType($parameter) : $this->configuration['token_name'];
      if ($tokenName) {
        $this->tokenService->addTokenData($tokenName, $parameter);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'request' => RouteInterface::ROUTE_CURRENT,
      'parameter_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $this->requestFormField($form);
    $form['parameter_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of route parameter'),
      '#description' => $this->t('The routes and their parameters can be found in the <em>MODULE.routing.yml</em> file. Example for the route <em>entity.node.preview</em>: <em>/node/preview/{node_preview}/{view_mode_id}</em> where <em>node_preview</em> and <em>view_mode_id</em> are the parameter names.'),
      '#default_value' => $this->configuration['parameter_name'],
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The name of the token, the parameter value gets stored into.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['request'] = $form_state->getValue('request');
    $this->configuration['parameter_name'] = $form_state->getValue('parameter_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
