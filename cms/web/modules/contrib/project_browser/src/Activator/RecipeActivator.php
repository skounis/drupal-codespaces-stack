<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Composer\InstalledVersions;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\Core\Recipe\RecipeInputFormTrait;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies locally installed recipes.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class RecipeActivator implements InstructionsInterface, EventSubscriberInterface, TasksInterface {

  use InstructionsTrait;

  /**
   * The state key that stores the record of all applied recipes.
   *
   * @var string
   */
  private const STATE_KEY = 'project_browser.applied_recipes';

  public function __construct(
    private readonly string $appRoot,
    private readonly StateInterface $state,
    private readonly FileSystemInterface $fileSystem,
    private readonly ModuleExtensionList $moduleList,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onApply',
    ];
  }

  /**
   * Reacts when a recipe is applied to the site.
   *
   * @param \Drupal\Core\Recipe\RecipeAppliedEvent $event
   *   The event object.
   */
  public function onApply(RecipeAppliedEvent $event): void {
    $list = $this->state->get(static::STATE_KEY, []);
    $list[] = $this->fileSystem->realpath($event->recipe->path);
    $this->state->set(static::STATE_KEY, $list);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(Project $project): ActivationStatus {
    $path = $this->getPath($project);
    // Ensure we're getting the most up-to-date information.
    $this->state->resetCache();

    if (in_array($path, $this->state->get(static::STATE_KEY, []), TRUE)) {
      return ActivationStatus::Active;
    }
    if ($project->packageName === 'drupal/core') {
      // Recipes that are part of core are always present.
      return ActivationStatus::Present;
    }
    else {
      return is_string($path) ? ActivationStatus::Present : ActivationStatus::Absent;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Project $project): bool {
    // @see \Drupal\project_browser\Plugin\ProjectBrowserSource\Recipes
    return $project->type === ProjectType::Recipe;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(Project $project): ?array {
    $path = $this->getPath($project);
    if (!$path) {
      return NULL;
    }

    $recipe = Recipe::createFromDirectory($path);

    // If the recipe has input, return a response that will instruct the Svelte
    // app to redirect.
    $route_name = 'project_browser.recipe_input';
    // We need to check for `Recipe::$input` and the trait because these didn't
    // exist before core 10.4 and 11.1, respectively.
    // @todo Remove property_exists() and trait_exists() checks in
    //   https://www.drupal.org/i/3494848.
    if (property_exists(Recipe::class, 'input') && trait_exists(RecipeInputFormTrait::class) && $recipe->input->getDataDefinitions()) {
      $url = Url::fromRoute($route_name, options: [
        'query' => [
          'recipe' => $path,
        ],
      ]);
      return [
        new RedirectCommand($url->setAbsolute()->toString()),
      ];
    }

    RecipeRunner::processRecipe($recipe);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTasks(Project $project, ?string $source_id = NULL): array {
    $tasks = [];

    // Only expose tasks if the recipe has been applied.
    if ($this->getStatus($project) !== ActivationStatus::Active) {
      return $tasks;
    }

    // If the source is known, we can expose a task to re-apply the recipe.
    if ($source_id) {
      $url = Url::fromRoute('project_browser.activate')
        ->setOption('query', [
          // @see \Drupal\project_browser\Controller\ProjectBrowserEndpointController::activate()
          'projects' => $source_id . '/' . $project->id,
        ])
        // @see \Drupal\project_browser\ProjectBrowser\Normalizer::getActivationInfo()
        ->setOption('project_browser_ajax', TRUE)
        ->setAbsolute();
      $tasks[] = Link::fromTextAndUrl($this->t('Reapply'), $url);
    }

    // Recipes can only define follow-up tasks in Drupal 11.1.4 and later.
    // @todo Remove this check when Drupal 11.1.4 or later is the minimum
    //   supported version of core.
    if (method_exists(Recipe::class, 'getExtra')) {
      // We know the path is a string because, if it wasn't, the getStatus()
      // call above would have returned ActivationStatus::Absent.
      $path = $this->getPath($project);
      assert(is_string($path));
      $extra = Recipe::createFromDirectory($path)->getExtra('project_browser');

      $link_from_array = function (array $link): Link {
        $url = array_key_exists('uri', $link)
          ? Url::fromUri($link['uri'])
          : Url::fromRoute($link['route_name'], $link['route_parameters'] ?? []);
        $url->setOptions($link['options'] ?? []);
        return Link::fromTextAndUrl($link['text'], $url);
      };
      $tasks = array_merge($tasks, array_map($link_from_array, $extra['tasks'] ?? []));
    }
    return $tasks;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstructions(Project $project, ?string $source_id = NULL): string {
    $instructions = '<p>' . $this->t('To apply this recipe, run the following command at the command line:') . '</p>';

    $command = sprintf(
      "cd %s\n%s/php %s/core/scripts/drupal recipe %s",
      $this->appRoot,
      // cspell:ignore BINDIR
      PHP_BINDIR,
      $this->appRoot,
      $this->getPath($project),
    );
    $instructions .= $this->commandBox($command, 'apply');

    return $instructions;
  }

  /**
   * Returns the absolute path of an installed recipe, if known.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project object with info about the recipe.
   *
   * @return string|null
   *   The absolute local path of the recipe, or NULL if it's not installed.
   */
  private function getPath(Project $project): ?string {
    if ($project->packageName === 'drupal/core') {
      // The machine name is the directory name.
      // @see \Drupal\project_browser\Plugin\ProjectBrowserSource\Recipes::getProjects()
      return $this->appRoot . '/core/recipes/' . $project->machineName;
    }

    try {
      $path = InstalledVersions::getInstallPath($project->packageName);
    }
    catch (\OutOfBoundsException) {
      // If this is a test recipe, its package name will have a specific
      // prefix.
      if (str_starts_with($project->packageName, 'project-browser-test/')) {
        $path = $this->moduleList->getPath('project_browser') . '/tests/fixtures/' . $project->machineName;
      }
      else {
        // The package isn't installed, so we can't get the path.
        $path = NULL;
      }
    }

    return $path ? ($this->fileSystem->realpath($path) ?: NULL) : NULL;
  }

}
