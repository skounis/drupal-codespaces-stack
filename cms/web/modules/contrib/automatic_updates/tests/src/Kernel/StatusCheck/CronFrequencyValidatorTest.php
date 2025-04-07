<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use Drupal\Tests\automatic_updates\Kernel\TestCronUpdateRunner;

/**
 * @covers \Drupal\automatic_updates\Validator\CronFrequencyValidator
 * @group automatic_updates
 * @internal
 */
class CronFrequencyValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // In this test, we do not want to do an update. We're just testing that
    // cron is configured to run frequently enough to do automatic updates. So,
    // pretend we're already on the latest secure version of core.
    $this->setCoreVersion('9.8.1');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);
  }

  /**
   * Tests that nothing is validated if not needed.
   */
  public function testNoValidation(): void {
    $state = $this->container->get('state');
    $state->delete('system.cron_last');
    $state->delete('install_time');

    // If the method is 'web' but cron updates are disabled no validation is
    // needed.
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::DISABLED)
      ->set('unattended.method', 'web')
      ->save();
    $this->assertCheckerResultsFromManager([], TRUE);

    // If cron updates are enabled but the method is 'console' no validation is
    // needed.
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::ALL)
      ->set('unattended.method', 'console')
      ->save();
    $this->assertCheckerResultsFromManager([], TRUE);

    // If cron updates are enabled and the method is 'web' validation is needed.
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::ALL)
      ->set('unattended.method', 'web')
      ->save();
    $error = ValidationResult::createError([
      t('Cron has not run recently. For more information, see the online handbook entry for <a href="https://www.drupal.org/cron">configuring cron jobs</a> to run at least every 3 hours.'),
    ]);
    $this->assertCheckerResultsFromManager([$error], TRUE);
  }

  /**
   * Data provider for testLastCronRunValidation().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerLastCronRunValidation(): array {
    $error = ValidationResult::createError([
      t('Cron has not run recently. For more information, see the online handbook entry for <a href="https://www.drupal.org/cron">configuring cron jobs</a> to run at least every 3 hours.'),
    ]);

    return [
      'cron never ran' => [
        0,
        [$error],
      ],
      'cron ran four hours ago' => [
        time() - 14400,
        [$error],
      ],
      'cron ran an hour ago' => [
        time() - 3600,
        [],
      ],
    ];
  }

  /**
   * Tests validation based on the last cron run time.
   *
   * @param int $last_run
   *   A timestamp of the last time cron ran.
   * @param \Drupal\package_manager\ValidationResult[] $expected_results
   *   The expected validation results.
   *
   * @dataProvider providerLastCronRunValidation
   */
  public function testLastCronRunValidation(int $last_run, array $expected_results): void {
    $this->container->get('state')->set('system.cron_last', $last_run);
    $this->assertCheckerResultsFromManager($expected_results, TRUE);

    try {
      $this->container->get('cron')->run();
      $this->fail('Expected an exception but one was not thrown.');
    }
    catch (\BadMethodCallException $e) {
      // The terminal command cannot be run in a kernel test.
      // @see \Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase::runUpdateViaConsole
      $this->assertSame(TestCronUpdateRunner::class, $e->getMessage());
      // After cron runs, the validator should detect that cron ran recently.
      // Even though the terminal command did not succeed, the decorated cron
      // service from the System module should have been called before the
      // attempt to run the terminal command.
      // @see \Drupal\automatic_updates\CronUpdateRunner::run
      $this->assertCheckerResultsFromManager([], TRUE);
    }
  }

}
