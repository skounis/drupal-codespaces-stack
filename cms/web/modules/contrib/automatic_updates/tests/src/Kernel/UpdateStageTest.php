<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Kernel;

use Drupal\automatic_updates\UpdateStage;
use Drupal\package_manager\Exception\ApplyFailedException;
use Drupal\package_manager\Exception\StageException;
use Drupal\package_manager_bypass\LoggingCommitter;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PhpTuf\ComposerStager\API\Core\StagerInterface;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;
use PhpTuf\ComposerStager\API\Exception\InvalidArgumentException;

/**
 * @coversDefaultClass \Drupal\automatic_updates\UpdateStage
 * @group automatic_updates
 * @internal
 */
class UpdateStageTest extends AutomaticUpdatesKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Tests that correct versions are staged after calling ::begin().
   */
  public function testCorrectVersionsStaged(): void {
    // Simulate that we're running Drupal 9.8.0 and a security update to 9.8.1
    // is available.
    $this->setCoreVersion('9.8.0');
    $this->setReleaseMetadata([
      'drupal' => __DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml',
    ]);

    // Create a user who will own the stage even after the container is rebuilt.
    $user = $this->createUser([], NULL, TRUE, ['uid' => 2]);
    $this->setCurrentUser($user);

    $id = $this->container->get(UpdateStage::class)->begin([
      'drupal' => '9.8.1',
    ]);
    // Rebuild the container to ensure the package versions are persisted.
    /** @var \Drupal\Core\DrupalKernel $kernel */
    $kernel = $this->container->get('kernel');
    $kernel->rebuildContainer();
    $this->container = $kernel->getContainer();
    // Keep using the user account we created.
    $this->setCurrentUser($user);

    $stage = $this->container->get(UpdateStage::class);

    // Ensure that the target package versions are what we expect.
    $expected_versions = [
      'production' => [
        'drupal/core-recommended' => '9.8.1',
      ],
      'dev' => [
        'drupal/core-dev' => '9.8.1',
      ],
    ];
    $this->assertSame($expected_versions, $stage->claim($id)->getPackageVersions());

    // When we call UpdateStage::stage(), the stored project versions should be
    // read from state and passed to Composer Stager's Stager service, in the
    // form of a Composer command. This is done using package_manager_bypass's
    // invocation recorder, rather than a regular mock, in order to test that
    // the invocation recorder itself works.
    // The production requirements are changed first, followed by the dev
    // requirements. Then the installed packages are updated. This is tested
    // functionally in Package Manager.
    // @see \Drupal\Tests\package_manager\Build\StagedUpdateTest
    $expected_arguments = [
      [
        'require',
        '--no-update',
        'drupal/core-recommended:9.8.1',
      ],
      [
        'require',
        '--dev',
        '--no-update',
        'drupal/core-dev:9.8.1',
      ],
      [
        'update',
        '--with-all-dependencies',
        '--optimize-autoloader',
        'drupal/core-recommended:9.8.1',
        'drupal/core-dev:9.8.1',
      ],
    ];
    $stage->stage();

    $actual_arguments = $this->container->get(StagerInterface::class)
      ->getInvocationArguments();

    $this->assertCount(count($expected_arguments), $actual_arguments);
    foreach ($actual_arguments as $i => [$arguments]) {
      $this->assertSame($expected_arguments[$i], $arguments);
    }
  }

  /**
   * @covers ::begin
   *
   * @dataProvider providerInvalidProjectVersions
   */
  public function testInvalidProjectVersions(array $project_versions): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Currently only updates to Drupal core are supported.');
    $this->container->get(UpdateStage::class)->begin($project_versions);
  }

  /**
   * Data provider for testInvalidProjectVersions().
   *
   * @return mixed[][]
   *   The test cases.
   */
  public static function providerInvalidProjectVersions(): array {
    return [
      'only not drupal' => [['not_drupal' => '1.1.3']],
      'not drupal and drupal' => [['drupal' => '9.8.0', 'not_drupal' => '1.2.3']],
      'empty' => [[]],
    ];
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
        \RuntimeException::class,
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
   * @param string $thrown_class
   *   The throwable class that should be thrown by Composer Stager.
   * @param string|null $expected_class
   *   The expected exception class.
   *
   * @dataProvider providerCommitException
   */
  public function testCommitException(string $thrown_class, ?string $expected_class = NULL): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.1');

    $stage = $this->container->get(UpdateStage::class);
    $stage->begin([
      'drupal' => '9.8.1',
    ]);
    $stage->stage();
    $thrown_message = 'A very bad thing happened';
    // Composer Stager's exception messages are usually translatable, so they
    // need to be wrapped by a TranslatableMessage object.
    if (is_subclass_of($thrown_class, ExceptionInterface::class)) {
      $thrown_message = $this->createComposeStagerMessage($thrown_message);
    }
    LoggingCommitter::setException($thrown_class, $thrown_message, 123);
    $this->expectException($expected_class);
    $expected_message = $expected_class === ApplyFailedException::class ?
      "Automatic updates failed to apply, and the site is in an indeterminate state. Consider restoring the code and database from a backup."
      : $thrown_message;
    $this->expectExceptionMessage((string) $expected_message);
    $this->expectExceptionCode(123);
    $stage->apply();
  }

  /**
   * Tests that setLogger is called on the update stage service.
   */
  public function testLoggerIsSetByContainer(): void {
    $stage_method_calls = $this->container->getDefinition(UpdateStage::class)->getMethodCalls();
    $this->assertSame('setLogger', $stage_method_calls[0][0]);
  }

}
