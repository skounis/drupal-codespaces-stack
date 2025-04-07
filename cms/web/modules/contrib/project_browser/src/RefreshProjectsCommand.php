<?php

declare(strict_types=1);

namespace Drupal\project_browser;

use Drupal\Core\Ajax\CommandInterface;

/**
 * An AJAX command to refresh projects in the Svelte app.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
final class RefreshProjectsCommand implements CommandInterface {

  public function __construct(
    private readonly array $projects,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'refresh_projects',
      'projects' => $this->projects,
    ];
  }

}
