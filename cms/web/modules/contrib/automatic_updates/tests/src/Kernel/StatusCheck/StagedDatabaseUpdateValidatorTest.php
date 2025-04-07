<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel\StatusCheck;

use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\Tests\automatic_updates\Kernel\AutomaticUpdatesKernelTestBase;
use ColinODell\PsrTestLogger\TestLogger;

/**
 * @covers \Drupal\automatic_updates\Validator\StagedDatabaseUpdateValidator
 * @group automatic_updates
 * @internal
 */
class StagedDatabaseUpdateValidatorTest extends AutomaticUpdatesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates'];

  /**
   * Tests that unattended updates are stopped by staged database updates.
   */
  public function testStagedDatabaseUpdateExists(): void {
    $logger = new TestLogger();
    $this->container->get('logger.channel.automatic_updates')
      ->addLogger($logger);

    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');

    $listener = function (PreApplyEvent $event): void {
      $dir = $event->stage->getStageDirectory() . '/core/modules/system';
      mkdir($dir, 0777, TRUE);
      file_put_contents($dir . '/system.install', "<?php\nfunction system_update_10101010() {}");
    };
    $this->addEventTestListener($listener);

    $this->runConsoleUpdateStage();
    $this->assertExceptionLogged("The update cannot proceed because database updates have been detected in the following extensions.\nSystem\n", $logger);
  }

}
