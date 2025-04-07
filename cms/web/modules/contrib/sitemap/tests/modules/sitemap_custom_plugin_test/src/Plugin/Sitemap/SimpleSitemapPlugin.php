<?php

namespace Drupal\sitemap_custom_plugin_test\Plugin\Sitemap;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sitemap\SitemapBase;

/**
 * An un-derived plugin, for adding a single, custom section.
 *
 * @Sitemap(
 *   id = "sitemap_custom_plugin_test_simple",
 *   title = @Translation("Test simple plugin"),
 *   description = @Translation("An un-derived, test sitemap plugin."),
 *   settings = {
 *     "title" = @Translation("Test simple plugin"),
 *     "fizz" = "buzz",
 *   },
 *   enabled = TRUE,
 * )
 */
class SimpleSitemapPlugin extends SitemapBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Define a custom setting.
    $form['fizz'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fizz'),
      '#default_value' => $this->settings['fizz'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    // Display a sitemap item with the title and custom setting, linked to the
    // administrative list of content.
    return [
      '#theme' => 'sitemap_item',
      '#title' => $this->settings['title'],
      '#content' => [
        '#theme' => 'sitemap_frontpage_item',
        '#text' => Html::escape($this->settings['fizz']),
        '#url' => Url::fromRoute('system.admin_content'),
      ],
      '#sitemap' => $this,
    ];
  }

}
