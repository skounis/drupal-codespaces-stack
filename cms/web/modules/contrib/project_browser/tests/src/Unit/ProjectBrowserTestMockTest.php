<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\project_browser_test\Plugin\ProjectBrowserSource\ProjectBrowserTestMock;
use Psr\Log\LoggerInterface;

/**
 * Tests plugin functions.
 *
 * @group project_browser
 */
final class ProjectBrowserTestMockTest extends UnitTestCase {

  /**
   * The plugin.
   *
   * @var \Drupal\project_browser_test\Plugin\ProjectBrowserSource\ProjectBrowserTestMock
   */
  protected $plugin;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The state object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->state = $this->createMock('\Drupal\Core\State\StateInterface');
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $configuration = [];
    $plugin_id = $this->randomMachineName();
    $plugin_definition = [];
    $this->plugin = new ProjectBrowserTestMock($configuration, $plugin_id, $plugin_definition, $this->logger, $this->database, $this->state, $this->moduleHandler);
  }

  /**
   * Gets a protected/private method to test.
   *
   * @param string $name
   *   The method name.
   *
   * @return \ReflectionMethod
   *   The accessible method.
   */
  protected static function getMethod($name): \ReflectionMethod {
    $class = new \ReflectionClass(ProjectBrowserTestMock::class);
    $method = $class->getMethod($name);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Tests relative to absolute URL conversion.
   */
  public function testRelativeToAbsoluteUrl(): void {
    // Project body with relative URLs.
    $project_data['body'] = ['value' => '<img src="/files/issues/123" alt="Image1" /><img src="/files/issues/321" alt="Image2" />'];
    // Expected Absolute URLs.
    $method = self::getMethod('relativeToAbsoluteUrls');
    $after_conversion = $method->invokeArgs($this->plugin, [$project_data['body'], 'https://www.drupal.org']);
    $this->assertStringContainsString('src="https://www.drupal.org/files/issues/123"', $after_conversion['value']);
    $this->assertStringContainsString('src="https://www.drupal.org/files/issues/321"', $after_conversion['value']);
  }

}
