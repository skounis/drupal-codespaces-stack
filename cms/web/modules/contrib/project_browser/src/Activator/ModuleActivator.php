<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Composer\InstalledVersions;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectType;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An activator for Drupal modules.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class ModuleActivator implements InstructionsInterface, TasksInterface {

  use InstructionsTrait;

  public function __construct(
    private readonly ModuleInstallerInterface $moduleInstaller,
    private ModuleExtensionList $moduleList,
    private ModuleHandlerInterface $moduleHandler,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    if ($this->moduleHandler->moduleExists($project->machineName)) {
      return ActivationStatus::Active;
    }
    elseif (array_key_exists($project->machineName, $this->moduleList->getAllAvailableInfo())) {
      return ActivationStatus::Present;
    }
    return ActivationStatus::Absent;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    return $project->type === ProjectType::Module;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    $this->moduleInstaller->install([$project->machineName]);

    // The container has changed, so we need to reload the module handler and
    // module list from the global service wrapper.
    // @phpstan-ignore-next-line
    $this->moduleHandler = \Drupal::moduleHandler();
    // @phpstan-ignore-next-line
    $this->moduleList = \Drupal::service(ModuleExtensionList::class);

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstructions(Project $project, ?string $source_id = NULL): string {
    if ($this->getStatus($project) === ActivationStatus::Present) {
      return Url::fromRoute('system.modules_list')
        ->setOption('fragment', 'module-' . str_replace('_', '-', $project->machineName))
        ->setAbsolute()
        ->toString();
    }

    $commands = '<h3>' . $this->t('1. Download') . '</h3>';
    $commands .= '<p>';
    $commands .= $this->t('The <a href="@use" target="_blank" rel="noreferrer noopener">recommended way</a> to download any Drupal module is with <a href="@get" target="_blank" rel="noreferrer noopener">Composer</a>.', [
      '@use' => 'https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies#managing-contributed',
      '@get' => 'https://getcomposer.org',
    ]);
    $commands .= '</p>';
    $commands .= '<p>' . $this->t("If you already manage your Drupal application dependencies with Composer, run the following from the command line in your application's Composer root directory:") . '</p>';
    $commands .= $this->commandBox('composer require ' . $project->packageName, 'download');
    $commands .= '<p>' . $this->t('This will download the module to your codebase.') . '</p>';
    $commands .= '<p>';
    $commands .= $this->t('Didn\'t work? <a href="@url" target="_blank" rel="noreferrer noopener">Learn how to troubleshoot Composer</a>.', [
      '@url' => 'https://getcomposer.org/doc/articles/troubleshooting.md',
    ]);
    $commands .= '</p>';
    $commands .= '<h3>' . $this->t('2. Install') . '</h3>';
    $commands .= '<p>';
    $commands .= $this->t('Go to the <a href="@url" target="_blank" rel="noreferrer noopener">Extend page</a> (admin/modules), check the box next to each module you wish to enable, then click the Install button at the bottom of the page.', [
      '@url' => Url::fromRoute('system.modules_list')->toString(),
    ]);
    $commands .= '</p>';
    $commands .= '<p>';
    $commands .= $this->t('Alternatively, you can use <a href="@url" target="_blank" rel="noreferrer noopener">Drush</a> to install it via the command line.', [
      '@url' => 'https://www.drush.org/latest',
    ]);
    $commands .= '</p>';

    $command = '';
    // Only show the command to install Drush if necessary.
    if (!in_array('drush/drush', InstalledVersions::getInstalledPackages(), TRUE)) {
      $command .= "composer require drush/drush\n";
    }
    $command .= 'drush install ' . $project->machineName;

    $commands .= $this->commandBox($command, 'install');
    return $commands;
  }

  /**
   * {@inheritdoc}
   */
  public function getTasks(Project $project, ?string $source_id = NULL): array {
    $tasks = [];

    // If the module isn't active, there's nothing for the user to do.
    if ($this->getStatus($project) !== ActivationStatus::Active) {
      return $tasks;
    }

    $info = $this->moduleList->getExtensionInfo($project->machineName);
    if (array_key_exists('configure', $info)) {
      $tasks[] = Link::createFromRoute($this->t('Configure'), $info['configure']);
    }
    if ($this->moduleHandler->moduleExists('help') && $this->moduleHandler->hasImplementations('help', $project->machineName)) {
      $tasks[] = Link::createFromRoute($this->t('Help'), 'help.page', [
        'name' => $project->machineName,
      ]);
    }

    $uninstall_url = Url::fromRoute('project_browser.uninstall')
      ->setRouteParameter('name', $project->machineName);

    $request = $this->requestStack->getCurrentRequest();
    if ($request?->query->has('source')) {
      $return_to_url = Url::fromRoute('project_browser.browse')
        ->setRouteParameter('source', $request->query->get('source'))
        ->toString();
      $uninstall_url->setOption('query', ['return_to' => $return_to_url]);
    }
    $tasks[] = Link::fromTextAndUrl($this->t('Uninstall'), $uninstall_url);

    return $tasks;
  }

}
