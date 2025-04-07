<?php

declare(strict_types=1);

namespace Drupal\automatic_updates_extensions\Form;

use Drupal\automatic_updates\Form\UpdateFormBase;
use Drupal\package_manager\ComposerInspector;
use Drupal\package_manager\Exception\StageFailureMarkerException;
use Drupal\package_manager\InstalledPackage;
use Drupal\package_manager\PathLocator;
use Drupal\package_manager\ProjectInfo;
use Drupal\package_manager\ValidationResult;
use Drupal\automatic_updates_extensions\BatchProcessor;
use Drupal\automatic_updates\BatchProcessor as AutoUpdatesBatchProcessor;
use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\Exception\StageOwnershipException;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a form to commit staged updates.
 *
 * @internal
 *   Form classes are internal and should not be used by external code.
 */
final class UpdateReady extends UpdateFormBase {

  public function __construct(
    private readonly ExtensionUpdateStage $stage,
    MessengerInterface $messenger,
    private readonly StateInterface $state,
    private readonly ModuleExtensionList $moduleList,
    private readonly RendererInterface $renderer,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ComposerInspector $composerInspector,
    private readonly PathLocator $pathLocator,
  ) {
    $this->setMessenger($messenger);
  }

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
      $container->get(ExtensionUpdateStage::class),
      $container->get('messenger'),
      $container->get('state'),
      $container->get('extension.list.module'),
      $container->get('renderer'),
      $container->get('event_dispatcher'),
      $container->get(ComposerInspector::class),
      $container->get(PathLocator::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $stage_id = NULL) {
    try {
      $this->stage->claim($stage_id);
    }
    catch (StageOwnershipException) {
      $this->messenger()->addError($this->t('Cannot continue the update because another Composer operation is currently in progress.'));
      return $form;
    }
    catch (StageFailureMarkerException $e) {
      $this->messenger()->addError($e->getMessage());
      return $form;
    }

    $messages = [];

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
    $form['package_updates'] = $this->showUpdates();
    $form['backup'] = [
      '#prefix' => '<strong>',
      '#type' => 'checkbox',
      '#title' => $this->t('Warning: Updating contributed modules or themes may leave your site inoperable or looking wrong.'),
      '#description' => $this->t('Back up your database and site before you continue. <a href=":backup_url">Learn how</a>. Each contributed module or theme may follow different standards for backwards compatibility, may or may not have tests, and may add or remove features in any release. For these reasons, it is highly recommended that you test this update in a development environment first.', [':backup_url' => 'https://www.drupal.org/node/22281']),
      '#required' => TRUE,
      '#default_value' => FALSE,
      '#suffix' => '</strong>',
    ];
    $form['maintenance_mode'] = [
      '#title' => $this->t('Perform updates with site in maintenance mode (strongly recommended)'),
      '#type' => 'checkbox',
      '#default_value' => TRUE,
    ];

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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Store maintenance_mode setting so we can restore it when done.
    $this->getRequest()
      ->getSession()
      ->set(AutoUpdatesBatchProcessor::MAINTENANCE_MODE_SESSION_KEY, $this->state->get('system.maintenance_mode'));

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
      $form_state->setRedirect('automatic_updates_extensions.report_update');
    }
    catch (StageException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Displays all projects that will be updated.
   *
   * @return mixed[][]
   *   A render array displaying packages that will be updated.
   */
  private function showUpdates(): array {
    // Get packages that were updated in the stage directory.
    $installed_packages = $this->composerInspector->getInstalledPackagesList($this->pathLocator->getProjectRoot());
    $staged_packages = $this->composerInspector->getInstalledPackagesList($this->stage->getStageDirectory());
    $updated_packages = $staged_packages->getPackagesWithDifferentVersionsIn($installed_packages);

    // Build a list of package names that were updated by user request.
    $updated_by_request = [];
    foreach ($this->stage->getPackageVersions() as $group) {
      $updated_by_request = array_merge($updated_by_request, array_keys($group));
    }

    $updated_by_request_info = [];
    $updated_project_info = [];
    $supported_package_types = ['drupal-module', 'drupal-theme'];

    // Compile an array of relevant information about the packages that will be
    // updated.
    foreach ($updated_packages as $name => $updated_package) {
      // Ignore anything that isn't a module or a theme.
      if (!in_array($updated_package->type, $supported_package_types, TRUE)) {
        continue;
      }
      $updated_project_info[$name] = [
        'title' => $this->getProjectTitleFromPackage($updated_package),
        'installed_version' => $installed_packages[$updated_package->name]->version,
        'updated_version' => $updated_package->version,
      ];
    }

    foreach ($updated_packages as $name => $updated_package) {
      // Sort the updated packages into two groups: the ones that were updated
      // at the request of the user, and the ones that got updated anyway
      // (probably due to Composer's dependency resolution).
      if (in_array($name, $updated_by_request, TRUE)) {
        $updated_by_request_info[$name] = $updated_project_info[$name];
        unset($updated_project_info[$name]);
      }
    }
    $output = [];
    if ($updated_by_request_info) {
      // Create the list of messages for the packages updated by request.
      $output['requested'] = $this->getUpdatedPackagesItemList($updated_by_request_info, $this->t('The following projects will be updated:'));
    }

    if ($updated_project_info) {
      // Create the list of messages for packages that were updated
      // incidentally.
      $output['dependencies'] = $this->getUpdatedPackagesItemList($updated_project_info, $this->t('The following dependencies will also be updated:'));
    }
    return $output;
  }

  /**
   * Gets the human-readable project title for a Composer package.
   *
   * @param \Drupal\package_manager\InstalledPackage $package
   *   The installed package.
   *
   * @return string
   *   The human-readable title of the project. If no project information is
   *   available, the package name is returned.
   */
  private function getProjectTitleFromPackage(InstalledPackage $package): string {
    $project_name = $package->getProjectName();
    if (!$project_name) {
      return $package->name;
    }
    $project_info = new ProjectInfo($project_name);
    $project_data = $project_info->getProjectInfo();
    if ($project_data) {
      return $project_data['title'];
    }
    else {
      return $package->name;
    }
  }

  /**
   * Generates an item list of packages that will be updated.
   *
   * @param array[] $updated_packages
   *   An array of packages that will be updated, each sub-array containing the
   *   project title, installed version, and target version.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $item_list_title
   *   The title of the generated item list.
   *
   * @return array
   *   A render array for the generated item list.
   */
  private function getUpdatedPackagesItemList(array $updated_packages, TranslatableMarkup $item_list_title): array {
    $create_message_for_project = function (array $project): TranslatableMarkup {
      return $this->t('@title from @from_version to @to_version', [
        '@title' => $project['title'],
        '@from_version' => $project['installed_version'],
        '@to_version' => $project['updated_version'],
      ]);
    };
    return [
      '#theme' => 'item_list',
      '#prefix' => '<p>' . $item_list_title . '</p>',
      '#items' => array_map($create_message_for_project, $updated_packages),
    ];
  }

}
