<?php

namespace Drupal\simple_sitemap_engines\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for IndexNow routes.
 */
class IndexNowController extends ControllerBase {

  /**
   * Sitemap submitting service.
   *
   * @var \Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter
   */
  protected $submitter;

  /**
   * IndexNowController constructor.
   *
   * @param \Drupal\simple_sitemap_engines\Submitter\IndexNowSubmitter $submitter
   *   Sitemap submitting service.
   */
  public function __construct(IndexNowSubmitter $submitter) {
    $this->submitter = $submitter;
  }

  /**
   * Return dynamically created text file content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   * @param string|null $key
   *   The IndexNow key from the request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function getKeyFile(Request $request, ?string $key): Response {
    if ($key
      && ($saved_key = $this->submitter->getKey())
      && $key === $saved_key) {
      $response = new Response($key);
      $response->headers->set('Content-Type', 'text/plain');

      return $response;
    }

    throw new NotFoundHttpException();
  }

}
