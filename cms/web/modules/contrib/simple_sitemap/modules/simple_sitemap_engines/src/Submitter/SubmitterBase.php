<?php

namespace Drupal\simple_sitemap_engines\Submitter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\simple_sitemap\Logger;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * Base class for submitter services.
 */
abstract class SubmitterBase {

  /**
   * The HTTP Client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Simple XML Sitemap logger.
   *
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * The Drupal state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * URL to be submitted.
   *
   * @var string
   */
  protected $url;

  /**
   * SitemapSubmitter constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The client used to submit to engines.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   Sitemap logger.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state service for last submitted.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    ClientInterface $http_client,
    Logger $logger,
    StateInterface $state,
    TimeInterface $time,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->state = $state;
    $this->time = $time;
    $this->config = $config_factory;
  }

  /**
   * Visits a URL and logs failures.
   *
   * @param string $url
   *   URL to visit.
   *
   * @return bool
   *   TRUE on success and FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function request(string $url): bool {
    $this->url = $url;
    try {
      $this->httpClient->request('GET', $url);
      $this->onSuccess();
      return TRUE;
    }
    catch (TransferException $e) {
      $this->logger->logException($e);
      $this->onFailure();
      return FALSE;
    }
  }

  /**
   * Actions to be performed on successful URL request.
   */
  protected function onSuccess(): void {
    $this->logger->m('Submission to @url was successful.', ['@url' => $this->url])->log();
  }

  /**
   * Actions to be performed on unsuccessful URL request.
   */
  protected function onFailure(): void {}

}
