<?php

declare(strict_types=1);

namespace Drupal\project_browser\ProjectBrowser;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\project_browser\ActivationManager;
use Drupal\project_browser\Activator\InstructionsInterface;
use Drupal\project_browser\Activator\TasksInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes Project and ProjectsResultsPage objects into an array format.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
final class Normalizer implements NormalizerInterface {

  public function __construct(
    private readonly ActivationManager $activationManager,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $data, ?string $format = NULL, array $context = []): array {
    if ($data instanceof Project) {
      assert(array_key_exists('source', $context));
      $source_id = $context['source'];
      $data = $this->getActivationInfo($data, $source_id, $context['account'] ?? NULL) + $data->toArray();
      $data['id'] = $source_id . '/' . $data['id'];
    }
    elseif ($data instanceof ProjectsResultsPage) {
      $context['source'] = $data->pluginId;

      $data = $data->toArray();
      $data['list'] = array_map(
        fn (Project $project): array => $this->normalize($project, $format, $context),
        $data['list'],
      );
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization(mixed $data, ?string $format = NULL, array $context = []): bool {
    return $data instanceof Project || $data instanceof ProjectsResultsPage;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      Project::class => TRUE,
      ProjectsResultsPage::class => TRUE,
    ];
  }

  /**
   * Gets activation information for a project, for delivery to the front-end.
   *
   * @param \Drupal\project_browser\ProjectBrowser\Project $project
   *   A project object.
   * @param string $source_id
   *   The ID of the source plugin that exposed the project.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account, or NULL to use the current user.
   *
   * @return array
   *   An array of activation information. Will consist of:
   *   - `status`: The activation status of the project on the current site.
   *     Will be the lowercase name of the one of the cases of
   *     \Drupal\project_browser\Activator\ActivationStatus.
   *   - `commands`: The instructions a human can take to activate the project
   *     manually, or a URL where they can do so. Will be NULL if the registered
   *     activator which supports the given project is not capable of generating
   *     instructions.
   *   - `tasks`: An array of \Drupal\Core\Link objects for specific follow-up
   *      tasks that a user can take after activating this project. For example,
   *      could include a link to a module's configuration form, or a dashboard
   *      provided by a recipe.
   *
   * @see \Drupal\project_browser\ProjectBrowser\Project::toArray()
   */
  private function getActivationInfo(Project $project, string $source_id, ?AccountInterface $account): array {
    $activator = $this->activationManager->getActivatorForProject($project);
    $data = [
      'status' => strtolower($activator->getStatus($project)->name),
      'commands' => NULL,
      'tasks' => [],
    ];

    if ($activator instanceof InstructionsInterface) {
      $data['commands'] = Xss::filter(
        $activator->getInstructions($project, $source_id),
        [...Xss::getAdminTagList(), 'textarea', 'button'],
      );
    }

    if ($activator instanceof TasksInterface) {
      // Filter out any tasks that the current user cannot access.
      $tasks = array_filter(
        $activator->getTasks($project, $source_id),
        fn (Link $link): bool => (bool) $link->getUrl()->access($account),
      );

      $map = function (Link $link): array {
        $text = $link->getText();
        if (is_array($text)) {
          $text = $this->renderer->renderInIsolation($text);
        }
        $url = $link->getUrl()->setAbsolute();
        return [
          'text' => (string) $text,
          'url' => $url->toString(),
          'ajax' => (bool) $url->getOption('project_browser_ajax'),
        ];
      };
      $data['tasks'] = array_values(array_map($map, $tasks));
    }

    return $data;
  }

}
