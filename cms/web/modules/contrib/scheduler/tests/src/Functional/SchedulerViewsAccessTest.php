<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests access to the scheduled content overview page and user tab.
 *
 * @group scheduler
 */
class SchedulerViewsAccessTest extends SchedulerBrowserTestBase {

  /**
   * Additional modules required.
   *
   * @var array
   */
  protected static $modules = ['views'];

  /**
   * The web user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * The scheduler editor user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $schedulerEditor;

  /**
   * The scheduler viewer user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $schedulerViewer;

  /**
   * Create users and scheduled content for the entity type being tested.
   */
  protected function createScheduledItems($entityTypeId, $bundle) {
    // For backwards-compatibility the node permission names have to end with
    // 'nodes' and 'content'. For all other entity types we use $entityTypeId.
    if ($entityTypeId == 'node') {
      $edit_key = 'nodes';
      $view_key = 'content';
    }
    else {
      $edit_key = $view_key = $entityTypeId;
    }
    // "view own unpublished $view_key" is needed for Products. It is not
    // required for Node or Media, and does not exist for Taxonomy terms.
    $base_permissions = ($entityTypeId == 'commerce_product') ? ["view own unpublished $view_key"] : [];

    $this->webUser = $this->drupalCreateUser();
    $this->webUser->set('name', 'Webisa the Web User')->save();

    $this->schedulerEditor = $this->drupalCreateUser(array_merge($base_permissions, ["schedule publishing of $edit_key"]));
    $this->schedulerEditor->set('name', 'Eddie the Scheduler Editor')->save();

    $this->schedulerViewer = $this->drupalCreateUser(array_merge($base_permissions, ["view scheduled $view_key"]));
    $this->schedulerViewer->set('name', 'Vicenza the Scheduler Viewer')->save();

    $this->addPermissionsToUser($this->adminUser, ['access user profiles']);

    // Create content scheduled for publishing and for unpublishing. The first
    // two are authored by schedulerEditor, the second two by schedulerViewer.
    $this->createEntity($entityTypeId, $bundle, [
      'title' => "$entityTypeId created by Scheduler Editor for publishing",
      'uid' => $this->schedulerEditor->id(),
      'status' => FALSE,
      'publish_on' => strtotime('+1 week'),
    ]);
    $this->createEntity($entityTypeId, $bundle, [
      'title' => "$entityTypeId created by Scheduler Editor for unpublishing",
      'uid' => $this->schedulerEditor->id(),
      'status' => TRUE,
      'unpublish_on' => strtotime('+1 week'),
    ]);
    $this->createEntity($entityTypeId, $bundle, [
      'title' => "$entityTypeId created by Scheduler Viewer for publishing",
      'uid' => $this->schedulerViewer->id(),
      'status' => FALSE,
      'publish_on' => strtotime('+1 week'),
    ]);
    $this->createEntity($entityTypeId, $bundle, [
      'title' => "$entityTypeId created by Scheduler Viewer for unpublishing",
      'uid' => $this->schedulerViewer->id(),
      'status' => TRUE,
      'unpublish_on' => strtotime('+1 week'),
    ]);
  }

  /**
   * Tests the scheduled content tab on the user page.
   *
   * @dataProvider dataViewScheduledContentUser
   */
  public function testViewScheduledContentUser($entityTypeId, $bundle) {
    $this->createScheduledItems($entityTypeId, $bundle);
    $url_end = ($entityTypeId == 'node') ? 'scheduled' : "scheduled_{$entityTypeId}";
    $assert = $this->assertSession();

    // Try to access a scheduled content user tab as an anonymous visitor. This
    // should not be allowed, and will give "403 Access Denied".
    $this->drupalGet("user/{$this->schedulerEditor->id()}/$url_end");
    $assert->statusCodeEquals(403);

    // Try to access a user's own scheduled content tab when they do not have
    // any scheduler permissions. This should give "403 Access Denied".
    $this->drupalLogin($this->webUser);
    $this->drupalGet("user/{$this->webUser->id()}/$url_end");
    $assert->statusCodeEquals(403);

    // Access a user's own scheduled content tab when they have only
    // 'schedule publishing of {type}' permission. This should give "200 OK".
    $this->drupalLogin($this->schedulerEditor);
    $this->drupalGet("user/{$this->schedulerEditor->id()}/$url_end");
    $assert->statusCodeEquals(200);
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for publishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for unpublishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Viewer for publishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Viewer for unpublishing");

    // Access another user's scheduled content tab. This should not be possible
    // and will give "403 Access Denied".
    $this->drupalGet("user/{$this->schedulerViewer->id()}/$url_end");
    $assert->statusCodeEquals(403);

    // Try to access a user's own scheduled content tab when that user only has
    // 'view scheduled {type}' and not 'schedule publishing of {type}'. This is
    // allowed and should give "200 OK" and show the users scheduled items.
    $this->drupalLogin($this->schedulerViewer);
    $this->drupalGet("user/{$this->schedulerViewer->id()}/$url_end");
    $assert->statusCodeEquals(200);
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Editor for publishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Editor for unpublishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for publishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for unpublishing");

    // Access another user's scheduled content tab. This should not be possible
    // and will give "403 Access Denied".
    $this->drupalGet("user/{$this->schedulerEditor->id()}/$url_end");
    $assert->statusCodeEquals(403);

    // Log in as Admin who has 'access user profiles' permission and access the
    // user who can schedule content. This is allowed and the content just for
    // that user should be listed.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet("user/{$this->schedulerEditor->id()}/$url_end");
    $assert->statusCodeEquals(200);
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for publishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for unpublishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Viewer for publishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Viewer for unpublishing");

    // Try to access the scheduled tab for a user who cannot schedule content
    // themselves but can view their scheduled content if scheduled by someone
    // else. This should give "200 OK" and the scheduled items will be shown.
    $this->drupalGet("user/{$this->schedulerViewer->id()}/$url_end");
    $assert->statusCodeEquals(200);
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Editor for publishing");
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Editor for unpublishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for publishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for unpublishing");

    // Access the scheduled tab for the admin user, and check for the 'empty'
    // text. This verifies that the uid argument plugin is working. In 10.2 the
    // user id shown, but from 10.3+ the user entity is returned so the view
    // displays the user name instead.
    $this->drupalGet("user/{$this->adminUser->id()}/$url_end");
    $assert->statusCodeEquals(200);
    if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
      $assert->pageTextMatches("/No scheduled (content|media) for user {$this->adminUser->getDisplayName()}/");
    }
    else {
      $assert->pageTextMatches("/No scheduled (content|media) for user {$this->adminUser->id()}/");
    }

  }

  /**
   * Provides test data for user view test.
   *
   * There is no user view for scheduled Commerce Products or Taxonomy Terms so
   * these entity types are removed from the user view test.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataViewScheduledContentUser() {
    $data = self::dataStandardEntityTypes();
    unset($data['#commerce_product']);
    unset($data['#taxonomy_term']);
    return $data;
  }

  /**
   * Tests the scheduled content overview.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testViewScheduledContentOverview($entityTypeId, $bundle) {
    $this->createScheduledItems($entityTypeId, $bundle);
    $scheduled_url = $this->adminUrl('scheduled', $entityTypeId, $bundle);
    $assert = $this->assertSession();

    // Try to access the scheduled content overview as an anonymous visitor.
    $this->drupalGet($scheduled_url);
    $assert->statusCodeEquals(403);

    // Try to access the scheduled content overview as a user who has no
    // scheduler permissions. This should not be possible.
    $this->drupalLogin($this->webUser);
    $this->drupalGet($scheduled_url);
    $assert->statusCodeEquals(403);

    // Try to access the scheduled content overview as a user with only
    // 'schedule publishing of {type}' permission. This should not be possible.
    $this->drupalLogin($this->schedulerEditor);
    $this->drupalGet($scheduled_url);
    $assert->statusCodeEquals(403);

    // Access the scheduled content overview as a user who only has
    // 'view scheduled {type}' permission. This is allowed and they should see
    // the scheduled content for all users.
    $this->drupalLogin($this->schedulerViewer);
    $this->drupalGet($scheduled_url);
    $assert->statusCodeEquals(200);
    // Unpublished nodes, media items and taxonomy terms by other users are
    // listed but products are not. Therefore do not check for the unpublished
    // product by Scheduler Editor here.
    if ($entityTypeId != 'commerce_product') {
      $assert->pageTextContains("$entityTypeId created by Scheduler Editor for publishing");
    }
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for unpublishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for publishing");
    $assert->pageTextContains("$entityTypeId created by Scheduler Viewer for unpublishing");

    // Disable the scheduled view.
    $view_ids = [
      'node' => 'scheduler_scheduled_content',
      'media' => 'scheduler_scheduled_media',
      'commerce_product' => 'scheduler_scheduled_commerce_product',
      'taxonomy_term' => 'scheduler_scheduled_taxonomy_term',
    ];
    $view = $this->container->get('entity_type.manager')->getStorage('view')->load($view_ids[$entityTypeId]);
    $view->disable()->save();

    // Attempt to view the scheduled entity page. Interactively this gives a
    // '404 page not found' error, but in phpunit it is served with a 200 code.
    // However the page is empty so we can check that the content is not shown.
    $this->drupalGet($scheduled_url);
    $assert->pageTextNotContains("$entityTypeId created by Scheduler Editor for unpublishing");

    // Log in as admin and check that access to the overview page is unaffected.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $assert->statusCodeEquals(200);
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for unpublishing");

    // Delete the view and check again that the overview remains accessible.
    $view->delete();
    $this->drupalGet($this->adminUrl('collection', $entityTypeId, $bundle));
    $assert->statusCodeEquals(200);
    $assert->pageTextContains("$entityTypeId created by Scheduler Editor for unpublishing");
  }

}
