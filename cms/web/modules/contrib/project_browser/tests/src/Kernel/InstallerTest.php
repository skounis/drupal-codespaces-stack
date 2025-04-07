<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\package_manager\Kernel\PackageManagerKernelTestBase;
use Drupal\Tests\package_manager\Traits\ComposerStagerTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\fixture_manipulator\ActiveFixtureManipulator;
use Drupal\package_manager\Event\PreApplyEvent;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\Exception\StageEventException;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager\ValidationResult;
use Drupal\package_manager_bypass\LoggingCommitter;
use Drupal\package_manager_test_validation\EventSubscriber\TestSubscriber;
use Drupal\project_browser\ComposerInstaller\Installer;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;

/**
 * @coversDefaultClass \Drupal\project_browser\ComposerInstaller\Installer
 *
 * @group project_browser
 */
final class InstallerTest extends PackageManagerKernelTestBase {

  use UserCreationTrait;
  use ComposerStagerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'package_manager_test_validation',
    'project_browser',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    (new ActiveFixtureManipulator())
      ->addPackage([
        'name' => 'org/package-name',
        'version' => '9.8.3',
        'type' => 'drupal-module',
      ])
      ->removePackage('org/package-name')->commitChanges();
  }

  /**
   * Data provider for testCommitException().
   *
   * @return string[][]
   *   The test cases.
   */
  public static function providerCommitException(): array {
    return [
      'RuntimeException' => [
        'RuntimeException',
        ApplyFailedException::class,
      ],
      'InvalidArgumentException' => [
        InvalidArgumentException::class,
        StageException::class,
      ],
      'Exception' => [
        'Exception',
        ApplyFailedException::class,
      ],
    ];
  }

  /**
   * Tests exception handling during calls to Composer Stager commit.
   *
   * @param class-string<\Throwable> $thrown_class
   *   The throwable class that should be thrown by Composer Stager.
   * @param class-string<\Throwable> $expected_class
   *   The expected exception class.
   *
   * @dataProvider providerCommitException
   */
  public function testCommitException(string $thrown_class, string $expected_class): void {
    /** @var \Drupal\project_browser\ComposerInstaller\Installer $installer */
    $installer = $this->container->get(Installer::class);
    $installer->create();
    $installer->require(['org/package-name']);

    $message = $thrown_message = 'A very bad thing happened';
    // If the exception is a Composer Stager exception, the message needs to be
    // translatable.
    if (is_a($thrown_class, ExceptionInterface::class, TRUE)) {
      $thrown_message = $this->createComposeStagerMessage($message);
    }

    LoggingCommitter::setException($thrown_class, $thrown_message, 123);
    $this->expectException($expected_class);
    $expected_message = $expected_class === ApplyFailedException::class ?
      'Staged changes failed to apply, and the site is in an indeterminate state. It is strongly recommended to restore the code and database from a backup.'
      : $message;
    $this->expectExceptionMessage($expected_message);
    $this->expectExceptionCode(123);
    $installer->apply();
  }

  /**
   * Tests that validation errors are thrown as install exceptions.
   *
   * @covers ::dispatch
   */
  public function testInstallException(): void {
    /** @var \Drupal\project_browser\ComposerInstaller\Installer $installer */
    $installer = $this->container->get(Installer::class);
    $installer->create();
    $installer->require(['org/package-name']);
    $results = [
      ValidationResult::createError([new TranslatableMarkup('These are not the projects you are looking for.')]),
    ];
    TestSubscriber::setTestResult($results, PreApplyEvent::class);
    $this->expectException(StageEventException::class);
    $this->expectExceptionMessage('These are not the projects you are looking for.');
    $installer->apply();
  }

}
