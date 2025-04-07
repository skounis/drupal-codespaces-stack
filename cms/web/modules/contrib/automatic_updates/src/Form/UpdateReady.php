<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Form;

use Drupal\automatic_updates\BatchProcessor;
use Drupal\automatic_updates\UpdateStage;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Exception\StageFailureMarkerException;
use Drupal\package_manager\ValidationResult;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\Exception\StageOwnershipException;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a form to commit staged updates.
 *
 * @internal
 *   Form classes are internal and the form structure may change at any time.
 */
final class UpdateReady extends UpdateFormBase {

  public function __construct(
    private readonly UpdateStage $stage,
    private readonly StateInterface $state,
    private readonly RendererInterface $renderer,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ComposerInspector $composerInspector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'automatic_updates_update_ready_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(UpdateStage::class),
      $container->get('state'),
      $container->get('renderer'),
      $container->get('event_dispatcher'),
      $container->get(ComposerInspector::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $stage_id = NULL) {
    try {
      $this->stage->claim($stage_id);
    }
    catch (StageOwnershipException $e) {
      $this->messenger()->addError($e->getMessage());
      return $form;
    }
    catch (StageFailureMarkerException $e) {
      $this->messenger()->addError($e->getMessage());
      return $form;
    }

    $messages = [];

    try {
      $staged_core_packages = $this->composerInspector->getInstalledPackagesList($this->stage->getStageDirectory())
        ->getCorePackages()
        ->getArrayCopy();
    }
    catch (\Throwable) {
      $messages[MessengerInterface::TYPE_ERROR][] = $this->t('There was an error loading the pending update. Press the <em>Cancel update</em> button to start over.');
    }

    // Don't set any messages if the form has been submitted, because we don't
    // want them to be set during form submit.
    if (!$form_state->getUserInput()) {
      foreach ($messages as $type => $messages_of_type) {
        foreach ($messages_of_type as $message) {
          $this->messenger()->addMessage($message, $type);
        }
      }
    }

    $form['actions'] = [
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel update'),
        '#submit' => ['::cancel'],
      ],
      '#type' => 'actions',
    ];
    $form['stage_id'] = [
      '#type' => 'value',
      '#value' => $stage_id,
    ];

    if (empty($staged_core_packages)) {
      return $form;
    }

    $form['target_version'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Drupal core will be updated to %version', [
        '%version' => reset($staged_core_packages)->version,
      ]),
    ];
    $form['backup'] = [
      '#prefix' => '<strong>',
      '#markup' => $this->t('This cannot be undone, so it is strongly recommended to <a href=":url">back up your database and site</a> before continuing, if you haven\'t already.', [':url' => 'https://www.drupal.org/node/22281']),
      '#suffix' => '</strong>',
    ];
    if (!$this->state->get('system.maintenance_mode')) {
      $form['maintenance_mode'] = [
        '#title' => $this->t('Perform updates with site in maintenance mode (strongly recommended)'),
        '#type' => 'checkbox',
        '#default_value' => TRUE,
      ];
    }

    // Don't run the status checks once the form has been submitted.
    if (!$form_state->getUserInput()) {
      $results = $this->runStatusCheck($this->stage, $this->eventDispatcher);
      // This will have no effect if $results is empty.
      $this->displayResults($results, $this->renderer);
      // If any errors occurred, return the form early so the user cannot
      // continue.
      if (ValidationResult::getOverallSeverity($results) === SystemManager::REQUIREMENT_ERROR) {
        return $form;
      }
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
    ];
    $form['actions']['submit']['#button_type'] = 'primary';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Store maintenance_mode setting so we can restore it when done.
    $this->getRequest()
      ->getSession()
      ->set(BatchProcessor::MAINTENANCE_MODE_SESSION_KEY, $this->state->get('system.maintenance_mode'));

    if ($form_state->getValue('maintenance_mode')) {
      $this->state->set('system.maintenance_mode', TRUE);
    }

    $stage_id = $form_state->getValue('stage_id');
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Apply updates'))
      ->setInitMessage($this->t('Preparing to apply updates'))
      ->addOperation([BatchProcessor::class, 'commit'], [$stage_id])
      ->addOperation([BatchProcessor::class, 'postApply'], [$stage_id])
      ->addOperation([BatchProcessor::class, 'clean'], [$stage_id])
      ->setFinishCallback([BatchProcessor::class, 'finishCommit'])
      ->toArray();

    batch_set($batch);
  }

  /**
   * Cancels the in-progress update.
   */
  public function cancel(array &$form, FormStateInterface $form_state): void {
    try {
      $this->stage->destroy();
      $this->messenger()->addStatus($this->t('The update was successfully cancelled.'));
      $form_state->setRedirect('update.report_update');
    }
    catch (StageException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
