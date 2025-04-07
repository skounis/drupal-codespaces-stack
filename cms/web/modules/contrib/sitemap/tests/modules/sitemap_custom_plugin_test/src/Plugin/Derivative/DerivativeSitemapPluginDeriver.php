<?php

namespace Drupal\sitemap_custom_plugin_test\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A plugin deriver, for adding 1...N plugins (usually based on content/config).
 *
 * See https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-derivatives for
 * more information on plugin derivers.
 */
class DerivativeSitemapPluginDeriver extends DeriverBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * In the real world, you'd probably want to run a database or entity query,
   * loop over the results, and create derivatives inside the loop.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Create the first derivative.
    $this->derivatives['first'] = $base_plugin_definition;
    $this->derivatives['first']['title'] = $this->t('First derivative sitemap plugin');
    $this->derivatives['first']['foo'] = $this->t('Lorem ipsum dolor');

    // Create a second derivative.
    $this->derivatives['second'] = $base_plugin_definition;
    $this->derivatives['second']['title'] = $this->t('Second derivative sitemap plugin');
    $this->derivatives['second']['foo'] = $this->t('Sit amet adipiscing');

    return $this->derivatives;
  }

}
