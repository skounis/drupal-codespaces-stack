<?php

namespace Drupal\editoria11y\Plugin\views\area;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Warns if sync is disabled.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("editoria11y_sync_check")
 */
class SyncCheckMessage extends AreaPluginBase {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function preRender(array $results) {
    parent::preRender($results);

    // @phpstan-ignore-next-line
    $config = \Drupal::config('editoria11y.settings');

    // Force standard bool.
    $sync = $config->get('disable_sync');
    if (!!$sync) {
      $msg = t("Dashboard sync is disabled in Editoria11y configuration.");
      $this->messenger()->addWarning($msg);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    // Do nothing for this handler by returning an empty render array.
    return [];
  }

}
