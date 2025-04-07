<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads a query argument from the request into the token environment.
 *
 * @Action(
 *   id = "eca_token_load_query_arg",
 *   label = @Translation("Token: load query argument"),
 *   description = @Translation("Loads a query argument from the request into the token environment."),
 *   eca_version_introduced = "2.1.0"
 * )
 */
class TokenLoadQueryArgument extends ConfigurableActionBase {

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $argument_name = $this->tokenService->replace($this->configuration['argument_name']);
    $allowed = $this->request->query->has($argument_name);
    $result = AccessResult::allowedIf($allowed);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $argument_name = $this->tokenService->replace($this->configuration['argument_name']);
    $argument = $this->request->query->get($argument_name);
    $tokenName = empty($this->configuration['token_name']) ? $this->tokenService->getTokenType($argument) : $this->configuration['token_name'];
    if ($tokenName) {
      $this->tokenService->addTokenData($tokenName, $argument);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'argument_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['argument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of query argument'),
      '#default_value' => $this->configuration['argument_name'],
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The name of the token, the argument value gets stored into.'),
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
    $this->configuration['argument_name'] = $form_state->getValue('argument_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
