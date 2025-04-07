<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates\UpdateStage;

/**
 * Tests error message when the stage the user is interacting with is destroyed.
 *
 * @group automatic_updates
 * @internal
 */
class ErrorMessageOnStageDestroyTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['automatic_updates', 'automatic_updates_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $this->mockActiveCoreVersion('9.8.0');

    $this->drupalLogin($this->createUser([
      'administer site configuration',
      'administer software updates',
      'access site reports',
    ]));
  }

  /**
   * Tests error message on previous stage destroy.
   */
  public function testMessagesOnStageDestroy(): void {
    $this->getStageFixtureManipulator()
      ->setCorePackageVersion('9.8.1');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->checkForUpdates();
    $this->drupalGet('/admin/modules/update');
    $assert_session->buttonExists('Update to 9.8.1');
    $page->pressButton('Update to 9.8.1');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.1');
    $stage = $this->container->get(UpdateStage::class);
    $random_message = $this->randomString();
    // @see \Drupal\Tests\package_manager\Kernel\StageTest::testStoreDestroyInfo()
    // @see \Drupal\automatic_updates\CronUpdateRunner::performUpdate()
    $stage->destroy(TRUE, t($random_message));
    $this->checkForMetaRefresh();
    $page->pressButton('Continue');
    $assert_session->pageTextContains($random_message);
  }

}
