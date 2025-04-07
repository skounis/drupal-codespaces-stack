<?php

namespace Drupal\sitemap_book\Plugin\Sitemap;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitemap\SitemapBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a sitemap for a book.
 *
 * @Sitemap(
 *   id = "book",
 *   title = @Translation("Book name"),
 *   description = @Translation("Book type"),
 *   settings = {
 *     "title" = NULL,
 *     "show_expanded" = TRUE,
 *   },
 *   deriver = "Drupal\sitemap_book\Plugin\Derivative\BookSitemapDeriver",
 *   enabled = FALSE,
 *   book = "",
 * )
 */
class Book extends SitemapBase {

  /**
   * A book manager.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected BookManagerInterface $bookManager;

  /**
   * An entity type plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->bookManager = $container->get('book.manager');
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Provide the book name as the default title.
    $bid = $this->getPluginDefinition()['book'];
    $book = $this->entityTypeManager->getStorage('node')->load($bid);
    $form['title']['#default_value'] = $this->settings['title'] ?? $book->label();

    $form['show_expanded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show expanded'),
      '#default_value' => $this->settings['show_expanded'],
      '#description' => $this->t('Disable if you do not want to display the entire book outline on the sitemap.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $book_id = $this->pluginDefinition['book'];

    $max_depth = $this->settings['show_expanded'] ? NULL : 1;
    $tree = $this->bookManager->bookTreeAllData($book_id, NULL, $max_depth);
    $content = $this->bookManager->bookTreeOutput($tree);

    return ($tree) ? [
      '#theme' => 'sitemap_item',
      '#title' => $this->settings['title'],
      '#content' => $content,
      '#sitemap' => $this,
    ] : [];
  }

}
