<?php

namespace Drupal\simple_sitemap\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound and outbound sitemap paths.
 */
class SitemapPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $args = explode('/', $path ?? '');
    if (count($args) === 3 && $args[2] === 'sitemap.xml') {
      $path = '/sitemaps/' . $args[1] . '/sitemap.xml';
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $args = explode('/', $path ?? '');
    if (count($args) === 4 && $args[3] === 'sitemap.xml') {
      $path = '/' . $args[2] . '/sitemap.xml';
    }

    return $path;
  }

}
