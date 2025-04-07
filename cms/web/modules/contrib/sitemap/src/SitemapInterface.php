<?php

namespace Drupal\sitemap;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface definition for sitemap plugins.
 *
 * @ingroup sitemap
 */
interface SitemapInterface extends ConfigurableInterface, DependentPluginInterface, PluginInspectionInterface {

  /**
   * Returns a form to configure settings for the mapping.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the sitemap_map's settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Returns a short summary for the current mapping settings.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the mapping has configurable settings.
   *
   * @return string[]
   *   A short summary of the mapping settings.
   */
  public function settingsSummary();

  /**
   * Builds a renderable array for a sitemap item.
   *
   * @return array
   *   A renderable array for a themed field with its label and all its values.
   */
  public function view();

  /**
   * Returns the administrative label for this mapping plugin.
   *
   * @return string
   *   The label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for this mapping plugin.
   *
   * @return string
   *   The description.
   */
  public function getDescription();

}
