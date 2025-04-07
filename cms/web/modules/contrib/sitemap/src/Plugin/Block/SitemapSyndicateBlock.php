<?php

namespace Drupal\sitemap\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Syndicate (sitemap)' block.
 *
 * @Block(
 *   id = "sitemap_syndicate",
 *   label = @Translation("Syndicate"),
 *   admin_label = @Translation("Syndicate (sitemap)")
 * )
 */
class SitemapSyndicateBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->configFactory = $container->get('config.factory');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cache' => [
        // No caching.
        'max_age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->routeMatch->getRouteName();

    if ($route_name == 'blog.user_rss') {
      $feedUrl = Url::fromRoute('blog.user_rss', [
        'user' => $this->routeMatch->getParameter('user'),
      ]);
    }
    elseif ($route_name == 'blog.blog_rss') {
      $feedUrl = Url::fromRoute('blog.blog_rss');
    }
    else {
      $feedUrl = $this->configFactory->get('sitemap.settings')->get('rss_front');
    }

    $feed_icon = [
      '#theme' => 'feed_icon',
      '#url' => $feedUrl,
      '#title' => $this->t('Syndicate'),
    ];
    $output = $this->renderer->render($feed_icon);
    // Re-use drupal core's render element.
    $more_link = [
      '#type' => 'more_link',
      '#url' => Url::fromRoute('sitemap.page'),
      '#attributes' => ['title' => $this->t('View the sitemap to see more RSS feeds.')],
    ];
    $output .= $this->renderer->render($more_link);

    return [
      '#type' => 'markup',
      '#markup' => $output,
    ];
  }

}
