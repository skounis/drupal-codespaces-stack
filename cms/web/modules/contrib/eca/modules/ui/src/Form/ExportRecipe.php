<?php

namespace Drupal\eca_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Service\ExportRecipe as ExportRecipeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Export a model as a recipe.
 */
class ExportRecipe extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * The export recipe service.
   *
   * @var \Drupal\eca\Service\ExportRecipe
   */
  protected ExportRecipeService $exportRecipe;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = parent::create($container);
    $form->exportRecipe = $container->get('eca.export.recipe');
    $form->fileSystem = $container->get('file_system');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eca_export_recipe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Eca $eca = NULL): array {
    if ($eca === NULL) {
      return $form;
    }
    $form['eca'] = ['#type' => 'hidden', '#value' => $eca->id()];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->exportRecipe->defaultName($eca),
      '#required' => TRUE,
    ];
    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Namespace'),
      '#default_value' => ExportRecipeService::DEFAULT_NAMESPACE,
      '#required' => TRUE,
    ];
    $form['destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination'),
      '#default_value' => ExportRecipeService::DEFAULT_DESTINATION,
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export'),
      '#button_type' => 'primary',
    ];

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $destination = $form_state->getValue('destination');
    $configDestination = $destination . '/config';
    if (!file_exists($configDestination)) {
      if (!$this->fileSystem->mkdir($configDestination, FileSystem::CHMOD_DIRECTORY, TRUE)) {
        $form_state->setErrorByName('destination', $this->t('The destination does not exist or is not writable.'));
      }
    }
    elseif (!is_writable($configDestination)) {
      $form_state->setErrorByName('destination', $this->t('The destination is not writable.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Helper function to perform the export.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function doExport(FormStateInterface $form_state): void {
    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = Eca::load($form_state->getValue('eca'));
    $this->exportRecipe->doExport($eca, $form_state->getValue('name'), $form_state->getValue('namespace'), $form_state->getValue('destination'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->doExport($form_state);
    $form_state->setRedirect('entity.eca.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state): AjaxResponse {
    $this->doExport($form_state);
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new MessageCommand('The model has been exported as a recipe.'));
    return $response;
  }

}
