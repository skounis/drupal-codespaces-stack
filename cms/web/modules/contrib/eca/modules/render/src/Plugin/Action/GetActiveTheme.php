<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get the active theme.
 *
 * @Action(
 *   id = "eca_get_active_theme",
 *   label = @Translation("Get active theme"),
 *   description = @Translation("Get the currently active theme and store the value as a token."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetActiveTheme extends ActiveThemeActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Token name'),
      '#description' => $this->t('Specify the name of the token, that holds the name of the currently active theme.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -25,
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
  public function execute(): void {
    $this->tokenService->addTokenData($this->configuration['token_name'], $this->themeManager->getActiveTheme()->getName());
  }

}
