<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_misc\Plugin\RecipePathTrait;

/**
 * Loads a query argument from the request into the token environment.
 *
 * @Action(
 *   id = "eca_apply_recipe",
 *   label = @Translation("Recipe: apply"),
 *   description = @Translation("Applies a given recipe."),
 *   eca_version_introduced = "2.1.2"
 * )
 */
class ApplyRecipe extends ConfigurableActionBase {

  use RecipePathTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    if ($this->getRecipePath($this->configuration['recipe_package_name']) === NULL) {
      $result = AccessResult::forbidden('The configured package name is invalid.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $recipe = Recipe::createFromDirectory($this->getRecipePath($this->configuration['recipe_package_name']));
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'recipe_package_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['recipe_package_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipe package name'),
      '#description' => $this->t('The Composer package name of the recipe, that should be applied, e.g. "drupal/drupal_cms_privacy_basic"'),
      '#default_value' => $this->configuration['recipe_package_name'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['recipe_package_name'] = $form_state->getValue('recipe_package_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
