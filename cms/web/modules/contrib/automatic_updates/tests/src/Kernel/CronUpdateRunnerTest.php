<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel;

use Drupal\automatic_updates\CronUpdateRunner;

/**
 * @coversDefaultClass  \Drupal\automatic_updates\CronUpdateRunner
 * @group automatic_updates
 * @internal
 */
class CronUpdateRunnerTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
    'user',
    'common_test_cron_helper',
  ];

  /**
   * Tests that hook_cron implementations are always invoked.
   *
   * @covers ::run
   */
  public function testHookCronInvoked(): void {
    // Delete the state value set when cron runs to ensure next asserts start
    // from a good state.
    // @see \common_test_cron_helper_cron()
    $this->container->get('state')->delete('common_test.cron');

    // Undo override of the 'serverApi' property from the parent test class.
    // @see \Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase::setUp
    $property = new \ReflectionProperty(CronUpdateRunner::class, 'serverApi');
    $property->setValue(NULL, 'cli');
    $this->assertTrue(CronUpdateRunner::isCommandLine());

    // Since we're at the command line, the terminal command should not be
    // invoked. Since we are in a kernel test, there would be an exception
    // if that happened.
    // @see \Drupal\Tests\automatic_updates\Kernel\TestCronUpdateRunner::runTerminalUpdateCommand
    $this->container->get('cron')->run();
    // Even though the terminal command was not invoked, hook_cron
    // implementations should have been run.
    $this->assertCronRan();

    // If we are on the web but the method is set to 'console' the terminal
    // command should not be invoked.
    $property->setValue(NULL, 'cgi-fcgi');
    $this->assertFalse(CronUpdateRunner::isCommandLine());
    $this->config('automatic_updates.settings')
      ->set('unattended.method', 'console')
      ->save();
    $this->container->get('cron')->run();
    $this->assertCronRan();

    // If we are on the web and method settings is 'web' the terminal command
    // should be invoked.
    $this->config('automatic_updates.settings')
      ->set('unattended.method', 'web')
      ->save();
    try {
      $this->container->get('cron')->run();
      $this->fail('Expected an exception when running updates via cron.');
    }
    catch (\BadMethodCallException $e) {
      $this->assertSame(TestCronUpdateRunner::class, $e->getMessage());
    }
    // Even though the terminal command threw exception hook_cron
    // implementations should have been invoked before this.
    $this->assertCronRan();
  }

  /**
   * Asserts hook_cron implementations were invoked.
   *
   * @see \common_test_cron_helper_cron()
   */
  private function assertCronRan(): void {
    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('common_test_cron_helper'),
      '\Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase::assertCronRan can only be used if common_test_cron_helper is enabled.'
    );
    $state = $this->container->get('state');
    $this->assertSame('success', $state->get('common_test.cron'));
    // Delete the value so this function can be called again after the next cron
    // attempt.
    $state->delete('common_test.cron');
  }

}
