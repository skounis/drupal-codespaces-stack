<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

/**
 * Tests that only one Automatic Update operation can be performed at a time.
 *
 * @group automatic_updates
 * @internal
 */
class UpdateLockTest extends AutomaticUpdatesFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'automatic_updates',
    'automatic_updates_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([
      'administer site configuration',
    ]);
    $this->drupalLogin($user);
    $this->checkForUpdates();
  }

  /**
   * Tests that only user who started an update can continue through it.
   */
  public function testLock(): void {
    $this->getStageFixtureManipulator()->setCorePackageVersion('9.8.2');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->mockActiveCoreVersion('9.8.0');
    $this->checkForUpdates();
    $permissions = ['administer software updates'];
    $user_1 = $this->createUser($permissions);
    $user_2 = $this->createUser($permissions);

    // We should be able to get partway through an update without issue.
    $this->drupalLogin($user_1);
    $this->drupalGet('/admin/modules/update');
    $page->pressButton('Update');
    $this->checkForMetaRefresh();
    $this->assertUpdateReady('9.8.2');
    $assert_session->buttonExists('Continue');
    $url = $this->getSession()->getCurrentUrl();

    // Another user cannot show up and try to start an update, since the other
    // user already started one.
    $this->drupalLogin($user_2);
    $this->drupalGet('/admin/modules/update');
    $assert_session->buttonNotExists('Update');
    $assert_session->pageTextContains('Cannot begin an update because another Composer operation is currently in progress.');

    // If the current user did not start the update, they should not be able to
    // continue it, either.
    $this->drupalGet($url);
    $assert_session->pageTextContains('Cannot claim the stage because it is not owned by the current user or session.');
    $assert_session->buttonNotExists('Continue');

    // The user who started the update should be able to continue it.
    $this->drupalLogin($user_1);
    $this->drupalGet($url);
    $assert_session->pageTextNotContains('Cannot continue the update because another Composer operation is currently in progress.');
    $assert_session->buttonExists('Continue');
  }

}
