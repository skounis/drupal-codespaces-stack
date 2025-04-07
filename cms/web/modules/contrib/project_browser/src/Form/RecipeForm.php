<?php

declare(strict_types=1);

namespace Drupal\project_browser\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeConfigurator;
use Drupal\Core\Recipe\RecipeInputFormTrait;
use Drupal\Core\Recipe\RecipeRunner;

/**
 * Collects input for a recipe, then applies it.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class RecipeForm extends FormBase {

  use RecipeInputFormTrait;

  /**
   * Returns the recipe path stored in the current request.
   *
   * This expects that the query string will contain a `recipe` key, which has
   * the path to a locally installed recipe.
   *
   * @return \Drupal\Core\Recipe\Recipe
   *   The recipe stored in the current request.
   */
  private function getRecipe(): Recipe {
    // Clear the static recipe cache to prevent a bug.
    // @todo Remove this when https://drupal.org/i/3495305 is fixed.
    $reflector = new \ReflectionProperty(RecipeConfigurator::class, 'cache');
    $reflector->setValue(NULL, []);

    $path = $this->getRequest()->get('recipe');
    assert(is_dir($path));
    return Recipe::createFromDirectory($path);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $recipe = $this->getRecipe();
    $form += $this->buildRecipeInputForm($recipe);

    $form['#title'] = $this->t('Applying %recipe', [
      '%recipe' => $recipe->name,
    ]);
    $form['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $this->validateRecipeInput($this->getRecipe(), $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recipe = $this->getRecipe();
    $this->setRecipeInput($recipe, $form_state);

    $batch = (new BatchBuilder())
      ->setTitle(
        $this->t('Applying %recipe', ['%recipe' => $recipe->name]),
      );
    foreach (RecipeRunner::toBatchOperations($recipe) as [$callback, $arguments]) {
      $batch->addOperation($callback, $arguments);
    }
    // Redirect back to Project Browser when the batch job is done.
    $form_state->setRedirect('project_browser.browse', [
      'source' => 'recipes',
    ]);
    batch_set($batch->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_browser_apply_recipe_form';
  }

}
