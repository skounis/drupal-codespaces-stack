<?php

namespace Drupal\Tests\simple_sitemap_engines\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Prophecy\Argument;

// phpcs:disable Drupal.Arrays.Array.LongLineDeclaration

/**
 * Tests search engine sitemap submission.
 *
 * @group simple_sitemap_engines
 */
class SubmitSitemapTest extends KernelTestBase {

  /**
   * The modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'simple_sitemap', 'simple_sitemap_engines'];

  /**
   * The cron service.
   *
   * @var \Drupal\Core\Cron
   */
  protected $cron;

  /**
   * The search engine entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $engineStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('simple_sitemap_engine');
    $this->installConfig('simple_sitemap');
    $this->installConfig('simple_sitemap_engines');

    $this->cron = \Drupal::service('cron');
    $this->engineStorage = \Drupal::entityTypeManager()->getStorage('simple_sitemap_engine');
    $this->queue = \Drupal::queue('simple_sitemap_engine_submit');

    // Set Google to submit the default sitemap variant. Other search engines
    // will not submit anything.
    $google = $this->engineStorage->load('google');
    $google->sitemap_variants = ['default'];
    $google->save();
  }

  /**
   * Tests sitemap submission URLs and last submission status.
   */
  public function testSubmission() {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always succeed.
    $http_client->request('GET', Argument::any())->willReturn(TRUE);
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    $google = $this->engineStorage->load('google');
    $bing = $this->engineStorage->load('bing');

    // Check that Google was marked as submitted and Bing was not.
    $this->assertNotEmpty($google->last_submitted);
    $this->assertEmpty($bing->last_submitted);

    // Check that exactly 1 HTTP request was sent to the correct URL.
    $http_client->request('GET', 'http://www.google.com/ping?sitemap=http://localhost/default/sitemap.xml')->shouldBeCalled();
    $http_client->request('GET', Argument::any())->shouldBeCalledTimes(1);
  }

  /**
   * Tests that sitemaps are not submitted every time cron runs.
   */
  public function testNoDoubleSubmission() {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always succeed.
    $http_client->request('GET', Argument::any())->willReturn(TRUE);
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    // Check that Google was submitted and store its last submitted time.
    $google = $this->engineStorage->load('google');
    $http_client->request('GET', 'http://www.google.com/ping?sitemap=http://localhost/default/sitemap.xml')->shouldBeCalledTimes(1);
    $this->assertNotEmpty($google->last_submitted);
    $google_last_submitted = $google->last_submitted;

    // Make sure enough time passes between cron runs to guarantee that they
    // do not run within the same second, since timestamps are compared below.
    sleep(2);
    $this->cron->run();
    $google = $this->engineStorage->load('google');

    // Check that the last submitted time was not updated on the second cron
    // run.
    $this->assertEquals($google->last_submitted, $google_last_submitted);
    // Check that no duplicate request was sent.
    $http_client->request('GET', 'http://www.google.com/ping?sitemap=http://localhost/default/sitemap.xml')->shouldBeCalledTimes(1);
  }

  /**
   * Tests that failed sitemap submissions are handled properly.
   */
  public function testFailedSubmission() {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always fail.
    $http_client->request('GET', Argument::any())->willThrow(RequestException::class);
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    $google = $this->engineStorage->load('google');

    // Check that one request was attempted.
    $http_client->request('GET', Argument::any())->shouldBeCalledTimes(1);
    // Check the last submission time is still empty.
    $this->assertEmpty($google->last_submitted);
    // Check that the submission was removed from the queue despite failure.
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

}
