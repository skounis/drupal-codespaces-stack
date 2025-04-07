<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\package_manager\ValidationResult;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use Drupal\Tests\package_manager\Traits\PackageManagerBypassTestTrait;
use ColinODell\PsrTestLogger\TestLogger;

/**
 * @covers \Drupal\automatic_updates\Validator\PhpExtensionsValidator
 * @group automatic_updates
 * @internal
 */
class PhpExtensionsValidatorTest extends AutomaticUpdatesKernelTestBase {

  use PackageManagerBypassTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Tests warnings and/or errors if Xdebug is enabled.
   */
  public function testXdebugValidation(): void {
    $this->simulateXdebugEnabled();

    // Package Manager meekly warns about reduced performance when Xdebug is
    // enabled; Automatic Updates will actually prevent unattended updates.
    $warning_result = ValidationResult::createWarning([
      t('Xdebug is enabled, which may have a negative performance impact on Package Manager and any modules that use it.'),
    ]);
    $error_result = ValidationResult::createError([
      t("Unattended updates are not allowed while Xdebug is enabled. You cannot receive updates, including security updates, until it is disabled."),
    ]);

    $config = $this->config('automatic_updates.settings');

    // If unattended updates are disabled, we should only see a warning from
    // Package Manager.
    $config->set('unattended.level', CronUpdateRunner::DISABLED)->save();
    $this->assertCheckerResultsFromManager([$warning_result], TRUE);

    // The parent class' setUp() method simulates an available security update,
    // so ensure that the cron update runner will try to update to it.
    $config->set('unattended.level', CronUpdateRunner::SECURITY)->save();

    // If unattended updates are enabled, we should see an error from Automatic
    // Updates.
    $this->assertCheckerResultsFromManager([$error_result], TRUE);

    // Trying to do the update during cron should fail with an error.
    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);

    $this->runConsoleUpdateStage();
    // The update should have been stopped before it started.
    $this->assertUpdateStagedTimes(0);
    $this->assertExceptionLogged((string) $error_result->messages[0], $logger);
  }

  /**
   * Tests warnings and/or errors if Xdebug is enabled during pre-apply.
   */
  public function testXdebugValidationDuringPreApply(): void {
    // Xdebug will be enabled during pre-apply.
    $this->addEventTestListener($this->simulateXdebugEnabled(...));

    // The parent class' setUp() method simulates an available security
    // update, so ensure that the cron update runner will try to update to it.
    $this->config('automatic_updates.settings')
      ->set('unattended.level', CronUpdateRunner::SECURITY)
      ->save();

    $logger = new TestLogger();
    $this->container->get('logger.factory')
      ->get('automatic_updates')
      ->addLogger($logger);

    $this->runConsoleUpdateStage();
    // The update should have been staged, but then stopped with an error.
    $this->assertUpdateStagedTimes(1);
    $this->assertExceptionLogged("Unattended updates are not allowed while Xdebug is enabled. You cannot receive updates, including security updates, until it is disabled.", $logger);
  }

  /**
   * Simulating that xdebug is enabled.
   */
  private function simulateXdebugEnabled(): void {
    // @see \Drupal\package_manager\Validator\PhpExtensionsValidator::isExtensionLoaded()
    $this->container->get('state')
      ->set('package_manager_loaded_php_extensions', ['xdebug', 'openssl']);
  }

}
