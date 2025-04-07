<?php

namespace Drupal\sitemap_custom_plugin_test\Plugin\Sitemap;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sitemap\SitemapBase;

/**
 * A derived plugin, for adding 1...N sections (i.e.: based on content/config).
 *
 * See https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-derivatives for
 * more information on plugin derivers.
 *
 * @Sitemap(
 *   id = "sitemap_custom_plugin_test_derivative",
 *   title = @Translation("Test derived plugin"),
 *   description = @Translation("A derived, test sitemap plugin."),
 *   settings = {
 *     "title" = NULL,
 *     "bizz" = NULL,
 *   },
 *   deriver = "Drupal\sitemap_custom_plugin_test\Plugin\Derivative\DerivativeSitemapPluginDeriver",
 *   enabled = TRUE,
 * )
 */
class DerivativeSitemapPlugin extends SitemapBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Get the plugin definition created by the deriver.
    $pluginDef = $this->getPluginDefinition();

    // If there isn't an explicit title in the settings, then use the label
    // suggested by the deriver.
    // Note that if we had set the default "title" to something other than NULL
    // in this class' annotation, then there would be a non-NULL value inside
    // $this->settings['title'] on the first run, meaning we would never end up
    // using the value from the deriver. Once the config has been saved at least
    // once, then $this->settings['title'] will contain whatever the user
    // entered.
    $form['title']['#default_value'] = $this->settings['title'] ?? $pluginDef['title'];

    // Define a custom setting. If there isn't an explicit value in the
    // settings, then use the value suggested by the deriver.
    $form['bizz'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bizz'),
      '#default_value' => $this->settings['bizz'] ?? $pluginDef['foo'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    // Display a sitemap item with the title and custom setting, linked to the
    // administrative list of reports.
    return [
      '#theme' => 'sitemap_item',
      '#title' => $this->settings['title'],
      '#content' => [
        '#theme' => 'sitemap_frontpage_item',
        '#text' => Html::escape($this->settings['bizz']),
        '#url' => Url::fromRoute('system.admin_reports'),
      ],
      '#sitemap' => $this,
    ];
  }

}
