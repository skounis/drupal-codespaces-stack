<?php

namespace Drupal\simple_sitemap_engines\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Inbound path processor for IndexNow key requests.
 *
 * Make inbound IndexNow key text file requests go to a route that will
 * return a dynamically created text file with the IndexNow key.
 */
class IndexNowPathProcessor implements InboundPathProcessorInterface {

  /**
   * Sitemap submitting service.
   *
   * @var \Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter
   */
  protected $submitter;

  /**
   * IndexNowPathProcessor constructor.
   *
   * @param \Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter $submitter
   *   Sitemap submitting service.
   */
  public function __construct(IndexNowSubmitter $submitter) {
    $this->submitter = $submitter;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $args = explode('/', $path ?? '');

    if (count($args) === 2 && substr($args[1], -4) === '.txt') {
      $key = $this->submitter->getKey();

      if ($key && $key === substr($args[1], 0, -4)) {
        return "/simple_sitemap_engines/index_now_key/$key";
      }
    }

    return $path;
  }

}
