<?php

namespace Drupal\simple_sitemap\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\simple_sitemap\Manager\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for sitemap routes.
 */
class SimpleSitemapController extends ControllerBase {

  /**
   * The simple_sitemap.generator service.
   *
   * @var \Drupal\simple_sitemap\Manager\Generator
   */
  protected $generator;

  /**
   * SimpleSitemapController constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The simple_sitemap.generator service.
   */
  public function __construct(Generator $generator) {
    $this->generator = $generator;
  }

  /**
   * Returns a specific sitemap, its chunk, or its index.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string|null $variant
   *   Optional name of sitemap variant.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Returns an XML response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function getSitemap(Request $request, ?string $variant = NULL): Response {
    $defaultSitemap = $this->generator->getDefaultSitemap();
    $variant = $variant ?? ($defaultSitemap ? $defaultSitemap->id() : NULL);

    $page = $request->query->get('page') ? (int) $request->query->get('page') : NULL;
    $output = $this->generator->setSitemaps($variant)->getContent($page);
    if ($output === NULL) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableResponse($output, Response::HTTP_OK, [
      'Content-type' => 'application/xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, follow',
    ]);
    $response->getCacheableMetadata()
      ->addCacheTags(Cache::buildTags('simple_sitemap', (array) $variant))
      ->addCacheContexts(['url.query_args']);

    return $response;
  }

  /**
   * Returns the XML stylesheet for a sitemap.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Returns an XSL response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSitemapXsl(string $sitemap_generator): Response {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $manager = \Drupal::service('plugin.manager.simple_sitemap.sitemap_generator');
    try {
      $sitemap_generator = $manager->createInstance($sitemap_generator);
    }
    catch (PluginNotFoundException $ex) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface $sitemap_generator */
    if (NULL === ($xsl = $sitemap_generator->getXslContent())) {
      throw new NotFoundHttpException();
    }

    return new Response($xsl, Response::HTTP_OK, [
      'Content-type' => 'application/xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

}
