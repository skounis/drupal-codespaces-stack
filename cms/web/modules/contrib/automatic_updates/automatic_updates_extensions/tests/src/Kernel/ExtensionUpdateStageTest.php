<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates_extensions\Kernel;

use Drupal\automatic_updates_extensions\ExtensionUpdateStage;
use Drupal\automatic_updates_extensions\Validator\UpdateReleaseValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PhpTuf\ComposerStager\API\Core\StagerInterface;

/**
 * @coversDefaultClass \Drupal\automatic_updates_extensions\ExtensionUpdateStage
 * @group automatic_updates_extensions
 * @internal
 */
final class ExtensionUpdateStageTest extends AutomaticUpdatesExtensionsKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
    'automatic_updates_extensions',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // This test doesn't need to validate that the test projects used are in the
    // codebase. Therefore, we need to disable the following validators that
    // require real Drupal projects.
    $this->disableValidators[] = UpdateReleaseValidator::class;
    parent::setUp();
    $this->installEntitySchema('user');

    // Create a user who will own the stage even after the container is rebuilt.
    $user = $this->createUser([], NULL, TRUE, ['uid' => 2]);
    $this->setCurrentUser($user);
  }

  /**
   * Tests that correct versions are staged after calling ::begin().
   */
  public function testCorrectVersionsStaged(): void {
    $id = $this->container->get(ExtensionUpdateStage::class)->begin([
      'my_module' => '9.8.1',
      // Use a legacy version number to ensure they are converted to semantic
      // version numbers which will work with the drupal.org Composer facade.
      'my_dev_module' => '8.x-1.2-alpha1@alpha',
    ]);
    $user = $this->container->get('current_user')->getAccount();
    // Rebuild the container to ensure the package versions are persisted.
    /** @var \Drupal\Core\DrupalKernel $kernel */
    $kernel = $this->container->get('kernel');
    $kernel->rebuildContainer();
    $this->container = $kernel->getContainer();
    // Keep using the user account we created.
    $this->setCurrentUser($user);

    $stage = $this->container->get(ExtensionUpdateStage::class);

    // Ensure that the target package versions are what we expect.
    $expected_versions = [
      'production' => [
        'drupal/my_module' => '9.8.1',
      ],
      'dev' => [
        'drupal/my_dev_module' => '1.2.0-alpha1@alpha',
      ],
    ];
    $this->assertSame($expected_versions, $stage->claim($id)->getPackageVersions());

    // When we call ExtensionUpdateStage::stage(), the stored project versions
    // should be read from state and passed to Composer Stager's Stager service,
    // in the form of a Composer command. This is done using
    // package_manager_bypass's invocation recorder, rather than a regular mock,
    // in order to test that the invocation recorder itself works. The
    // production requirements are changed first, followed by the dev
    // requirements. Then the installed packages are updated. This is tested
    // functionally in Package Manager.
    // @see \Drupal\Tests\package_manager\Build\StagedUpdateTest
    $expected_arguments = [
      [
        'require',
        '--no-update',
        'drupal/my_module:9.8.1',
      ],
      [
        'require',
        '--dev',
        '--no-update',
        'drupal/my_dev_module:1.2.0-alpha1@alpha',
      ],
      [
        'update',
        '--with-all-dependencies',
        '--optimize-autoloader',
        'drupal/my_module:9.8.1',
        'drupal/my_dev_module:1.2.0-alpha1@alpha',
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
   * Tests that attempting to update an install profile throws an exception.
   */
  public function testUpdatingInstallProfile(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The project contrib_profile1 cannot be updated because updating install profiles is not supported.");

    $this->container->get(ExtensionUpdateStage::class)
      ->begin([
        'contrib_profile1' => '1.1.0',
      ]);
  }

  /**
   * Tests that an exception is thrown when calling begin() with no projects.
   */
  public function testNoProjectsInBegin(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('No projects to begin the update');
    $this->container->get(ExtensionUpdateStage::class)->begin([]);
  }

  /**
   * Tests exception if a Drupal project unknown to composer sent to ::begin().
   */
  public function testUnknownDrupalProject(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The project my_module_unknown is not a Drupal project known to Composer and cannot be updated.");
    $this->container->get(ExtensionUpdateStage::class)->begin([
      'my_module_unknown' => '9.8.1',
    ]);
  }

}
