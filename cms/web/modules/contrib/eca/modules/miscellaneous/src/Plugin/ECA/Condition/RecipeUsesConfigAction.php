<?php

namespace Drupal\eca_misc\Plugin\ECA\Condition;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca_misc\Plugin\RecipePathTrait;

/**
 * Plugin implementation for checking config actions in recipes.
 *
 * @EcaCondition(
 *   id = "eca_recipe_uses_config_action",
 *   label = @Translation("Recipe uses config action"),
 *   description = @Translation("Checks if a recipe uses a specific config action."),
 *   eca_version_introduced = "2.1.3"
 * )
 */
class RecipeUsesConfigAction extends ConditionBase {

  use RecipePathTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'recipe_package_name' => '',
      'config_action' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['recipe_package_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipe package name'),
      '#description' => $this->t('The Composer package name of the recipe, that needs to be checked, e.g. "drupal/drupal_cms_privacy_basic"'),
      '#default_value' => $this->configuration['recipe_package_name'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];

    $form['config_action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Config Action'),
      '#description' => $this->t('Enter the name of the config action you want to check for.'),
      '#default_value' => $this->configuration['config_action'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['recipe_package_name'] = $form_state->getValue('recipe_package_name');
    $this->configuration['config_action'] = $form_state->getValue('config_action');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $result = FALSE;
    $config_action = $this->tokenService->replace($this->configuration['config_action']);

    $recipe_path = $this->getRecipePath($this->configuration['recipe_package_name']);
    if ($recipe_path) {
      $recipe_contents = file_get_contents($recipe_path . '/recipe.yml');
      $recipe_data = Yaml::decode($recipe_contents);
      $result = !empty(array_filter($recipe_data['config']['actions'] ?? [], fn($action) => isset($action[$config_action])));
    }
    return $this->negationCheck($result);
  }

}
