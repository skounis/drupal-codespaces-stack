<?php

declare(strict_types=1);

namespace Drupal\project_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an attribute to identify Project Browser source plugins.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ProjectBrowserSource extends Plugin {

  /**
   * Constructs a ProjectBrowserSource attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin's human-readable name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A brief description of the plugin and what it does.
   * @param array|null $local_task
   *   A local task definition at which this source should be exposed in the UI.
   *   If NULL, the source will not be shown as a local task.
   * @param class-string|null $deriver
   *   The plugin's deriver class, if any.
   */
  public function __construct(
    string $id,
    public TranslatableMarkup $label,
    public TranslatableMarkup $description,
    public ?array $local_task = NULL,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
