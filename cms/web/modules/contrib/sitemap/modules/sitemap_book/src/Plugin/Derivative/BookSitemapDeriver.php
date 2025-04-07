<?php

namespace Drupal\sitemap_book\Plugin\Derivative;

use Drupal\book\BookManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 */
class BookSitemapDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected BookManagerInterface $bookManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->bookManager = $container->get('book.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->bookManager->getAllBooks() as $id => $book) {
      $this->derivatives[$id] = $base_plugin_definition;
      $this->derivatives[$id]['title'] = $this->t('Book: @book', ['@book' => $book['title']]);
      $this->derivatives[$id]['description'] = $book['type'];
      $this->derivatives[$id]['settings']['title'] = NULL;
      $this->derivatives[$id]['book'] = $id;
      $this->derivatives[$id]['config_dependencies']['config'] = ['book.settings'];
    }
    return $this->derivatives;
  }

}
