<?php

namespace Drupal\Tests\scheduler_content_moderation_integration\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;
use Drupal\commerce_product\Entity\ProductType;

/**
 * Base class from which all functional browser tests can be extended.
 *
 * @group scheduler_content_moderation_integration
 */
abstract class SchedulerContentModerationBrowserTestBase extends BrowserTestBase {

  use ContentModerationTestTrait;
  use SchedulerMediaSetupTrait;
  use SchedulerSetupTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'scheduler_content_moderation_integration',
    'content_moderation',
    'media',
    'commerce_product',
  ];

  /**
   * The moderation workflow.
   *
   * @var \Drupal\workflows\Entity\Workflow
   */
  protected $workflow;

  /**
   * The user with full permission to schedule node content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $schedulerUser;

  /**
   * The user without permission to schedule node content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $restrictedUser;

  /**
   * The user with full permission to schedule media content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $schedulerMediaUser;

  /**
   * The user without permission to schedule media content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $restrictedMediaUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ])
      ->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // Enable the scheduler fields in the default node form display, mimicking
    // what would be done if the entity bundle had been enabled via admin UI.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default'])
      ->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default'])
      ->save();

    // Use SchedulerMediaSetupTrait function for ease of creating Media a type.
    $this->createMediaType('audio_file', [
      'id' => 'soundtrack',
      'label' => 'Sound track',
    ])
      ->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // Enable the scheduler fields in the default media form display.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('media', 'soundtrack')
      ->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default'])
      ->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default'])
      ->save();

    // By default, media items cannot be viewed directly, the url media/id gives
    // 404 Not Found. Changing this setting makes debugging the tests easier.
    $configFactory = $this->container->get('config.factory');
    $configFactory->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save(TRUE);
    $this->container->get('router.builder')->rebuild();

    // Set the media file attachments to be optional not required, to simplify
    // editing and saving media entities during tests.
    $configFactory->getEditable('field.field.media.soundtrack.field_media_audio_file')
      ->set('required', FALSE)
      ->save(TRUE);

    // Create an editorial workflow and add the two test entity types to it.
    $this->workflow = $this->createEditorialWorkflow();
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $this->workflow->getTypePlugin()->addEntityTypeAndBundle('media', 'soundtrack');
    $this->workflow->save();

    // Enable the two state fields in the default node form display, mimicking
    // what would be done if the entity bundle had been enabled via admin UI.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent('publish_state', ['type' => 'scheduler_moderation'])
      ->setComponent('unpublish_state', ['type' => 'scheduler_moderation'])
      ->save();

    // Do the same for the two media state fields.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('media', 'soundtrack')
      ->setComponent('publish_state', ['type' => 'scheduler_moderation'])
      ->setComponent('unpublish_state', ['type' => 'scheduler_moderation'])
      ->save();

    // Define mediaStorage for use in SchedulerMediaSetupTrait functions.
    /** @var MediaStorageInterface $mediaStorage */
    $this->mediaStorage = $this->container->get('entity_type.manager')->getStorage('media');

    // Create a test product type that is enabled for scheduling. Commerce
    // products are not moderatable.
    ProductType::create([
      'id' => 'test_product',
      'label' => 'Flags',
    ])->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // Enable the scheduler fields in the default form display, mimicking what
    // would be done if the commerce_product had been enabled via admin UI.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('commerce_product', 'test_product')
      ->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default'])
      ->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default'])
      ->save();

    // Create an administrator user with entity configuration permissions.
    // 'access site reports' is required for admin/reports/dblog.
    // 'administer site configuration' is required for admin/reports/status.
    $this->adminUser = $this->drupalCreateUser([
      // General.
      'access site reports',
      'administer site configuration',
      // Node.
      'access content overview',
      'administer content types',
      // Media.
      'administer media types',
      'access media overview',
      // Commerce product.
      'administer commerce_product_type',
      'administer commerce_product',
    ]);
    $this->adminUser->set('name', 'Admin User')->save();

    // Create user with full permission to schedule node content and use all
    // editorial transitions.
    $this->schedulerUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit any page content',
      'schedule publishing of nodes',
      'view latest version',
      'view any unpublished content',
      'access content overview',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
    ]);
    $this->schedulerUser->set('name', 'Scheduler User')->save();

    // Create a restricted user without permission to schedule node content or
    // use the publish and archive transitions.
    $this->restrictedUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit own page content',
      'view latest version',
      'view any unpublished content',
      'access content overview',
      'use editorial transition create_new_draft',
    ]);
    $this->restrictedUser->set('name', 'Restricted User')->save();

    // Create media user with full permission to schedule media content and
    // use all editorial transitions.
    $this->schedulerMediaUser = $this->drupalCreateUser([
      'create soundtrack media',
      'edit any soundtrack media',
      'schedule publishing of media',
      'view latest version',
      'access media overview',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
    ]);
    $this->schedulerMediaUser->set('name', 'Scheduler Media User')->save();

    // Create a restricted media user without permission to schedule media or
    // use the publish and archive transitions.
    $this->restrictedMediaUser = $this->drupalCreateUser([
      'create soundtrack media',
      'edit any soundtrack media',
      'view latest version',
      'access media overview',
      'use editorial transition create_new_draft',
    ]);
    $this->restrictedMediaUser->set('name', 'Restricted Media User')->save();

  }

  /**
   * Returns the stored entity type object from a type id and bundle id.
   *
   * @param string $entityTypeId
   *   The machine name of the entity type, for example 'node' or 'media'.
   * @param string $bundle
   *   The machine name of the bundle, for example 'page' or 'soundtrack'.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The stored entity type object.
   */
  public function entityTypeObject(string $entityTypeId, string $bundle) {
    $entityTypeManager = $this->container->get('entity_type.manager');
    if ($definition = $entityTypeManager->getDefinition($entityTypeId)) {
      if ($bundle_entity_type = $definition->getBundleEntityType()) {
        if ($entityType = $entityTypeManager->getStorage($bundle_entity_type)->load($bundle)) {
          return $entityType;
        }
      }
    }
    // Show the incorrect parameter values.
    throw new \Exception(sprintf('Invalid entityTypeId "%s" and bundle "%s" combination passed to entityTypeObject()', $entityTypeId, $bundle));
  }

  /**
   * Test data for node and media entity types.
   *
   * @return array
   *   Each array item has the values: [entity type id, bundle id].
   */
  public static function dataEntityTypes(): array {
    return [
      '#node' => ['node', 'page'],
      '#media' => ['media', 'soundtrack'],
    ];
  }

}
