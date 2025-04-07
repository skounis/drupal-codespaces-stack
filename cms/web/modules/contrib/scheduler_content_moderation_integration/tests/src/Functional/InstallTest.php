<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;

/**
 * Test the install process of Scheduler Content Moderation Integration.
 *
 * This test class extends the core BrowserTestBase instead of the usual
 * SchedulerContentModerationBrowserTestBase, so that the SCMI module is not
 * installed during start-up.
 *
 * @group scheduler_content_moderation_integration
 */
class InstallTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use SchedulerMediaSetupTrait;
  use SchedulerSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The modules to load for this test.
   *
   * @var array
   */
  protected static $modules = ['scheduler', 'media'];

  /**
   * The moderation workflow.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // This is taken from SchedulerBrowserTestBase.
    $this->schedulerSetUp();
    if (stristr($this->toString(), 'media')) {
      $this->schedulerMediaSetUp();
    }
  }

  /**
   * Test the processing when SCMI is installed after Scheduler has been in use.
   *
   * @dataProvider dataInstallTest
   */
  public function testInstall($entityTypeId, $bundle) {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    // This is the user created in the Scheduler test base not SCMI test base.
    $this->drupalLogin($this->schedulerUser);

    // Check that both of the scheduler date fields are shown on the add form.
    $url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($url);
    $assert->statusCodeEquals(200);
    $assert->FieldExists('publish_on[0][value][date]');
    $assert->FieldExists('unpublish_on[0][value][date]');

    // Install scheduler_content_moderation_integration.
    \Drupal::service('module_installer')->install(['scheduler_content_moderation_integration']);

    // Create an editorial workflow and add the entity type bundle to it.
    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle($entityTypeId, $bundle);
    $this->workflow->save();

    $this->addPermissionsToUser($this->schedulerUser, [
      'use editorial transition publish',
      'use editorial transition archive',
    ]);

    // Check that both of the scheduler date fields are shown and also that both
    // of the SCMI state fields are displayed.
    $this->drupalGet($url);
    $assert->statusCodeEquals(200);
    $assert->FieldExists('publish_on[0][value][date]');
    $assert->FieldExists('unpublish_on[0][value][date]');
    $assert->FieldExists('publish_state[0]');
    $assert->FieldExists('unpublish_state[0]');
  }

  /**
   * Provides test data containing the standard entity types.
   *
   * These entity types are created in Scheduler test base not SCMI test base.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataInstallTest() {
    $data = [
      // cspell:disable-next-line
      '#node' => ['node', 'testpage'],
      '#media' => ['media', 'test_video'],
    ];
    return $data;
  }

}
