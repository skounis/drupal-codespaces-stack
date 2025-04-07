<?php

namespace Drupal\sitemap\Plugin\Sitemap;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sitemap\SitemapBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a link to the front page for the sitemap.
 *
 * @Sitemap(
 *   id = "frontpage",
 *   title = @Translation("Site front page"),
 *   description = @Translation("Displays a sitemap link for the site front page."),
 *   settings = {
 *     "title" = @Translation("Front page"),
 *     "rss" = "/rss.xml",
 *   },
 *   enabled = TRUE,
 * )
 */
class Frontpage extends SitemapBase {

  /**
   * A configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // @todo Convert to route instead of relative html path.
    $form['rss'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Feed URL'),
      '#default_value' => $this->settings['rss'],
      '#description' => $this->t('Specify the RSS feed for the front page. If you do not wish to display a feed, leave this field blank.'),
      '#access' => $this->currentUser->hasPermission('set front page rss link on sitemap'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $title = $this->settings['title'];

    $content[] = [
      '#theme' => 'sitemap_frontpage_item',
      '#text' => $this->t('Front page of %sn', [
        '%sn' => $this->configFactory->get('system.site')->get('name'),
      ]),
      '#url' => Url::fromRoute('<front>', [], ['html' => TRUE])->toString(),
      '#feed' => $this->settings['rss'],
    ];

    return [
      '#theme' => 'sitemap_item',
      '#title' => $title,
      '#content' => $content,
      '#sitemap' => $this,
    ];
  }

}
