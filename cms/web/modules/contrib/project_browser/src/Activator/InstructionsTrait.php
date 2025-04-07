<?php

declare(strict_types=1);

namespace Drupal\project_browser\Activator;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides helper methods for activators which generate instructions.
 *
 * @api
 *   This trait is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
trait InstructionsTrait {

  use StringTranslationTrait;

  /**
   * Generates the markup for a copy-and-paste terminal command.
   *
   * @param string $command
   *   A terminal command.
   * @param string $action
   *   An identifier of the action, like `download` or `run`.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $alt
   *   (optional) The alt text of the "copy" button. Defaults to "Copy the
   *   $action command".
   *
   * @return string
   *   The given command, in a format that can be copied and pasted.
   */
  protected function commandBox(string $command, string $action, ?TranslatableMarkup $alt = NULL): string {
    $rows = substr_count($command, "\n") + 1;

    $alt ??= $this->formatPlural(
      $rows,
      'Copy the @action command',
      'Copy the @action commands',
      ['@action' => $action],
    );

    $icon_url = $this->moduleList->getPath('project_browser') . '/images/copy-icon.svg';
    $icon_url = $this->fileUrlGenerator->generateString($icon_url);

    $command_box = '<div class="command-box">';
    $command_box .= '<textarea rows="' . $rows . '" readonly>' . $command . '</textarea>';
    $command_box .= '<button data-copy-command id="' . $action . '-btn">';
    $command_box .= '<img src="' . $icon_url . '" alt="' . $alt . '" />';
    $command_box .= '</button>';
    $command_box .= '</div>';
    return $command_box;
  }

}
