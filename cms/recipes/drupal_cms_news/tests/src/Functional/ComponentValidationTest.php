<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_news\Functional;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\drupal_cms_content_type_base\ContentModelTestTrait;

/**
 * @group drupal_cms_news
 */
class ComponentValidationTest extends BrowserTestBase {

  use ArraySubsetAsserts;
  use ContentModelTestTrait;
  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $dir = realpath(__DIR__ . '/../../..');
    // The recipe should apply cleanly.
    $this->applyRecipe($dir);
    // Apply it again to prove that it is idempotent.
    $this->applyRecipe($dir);

    $this->ensureFileExists('f57ca5cc-9336-44d5-b847-b24876ffd1f6');
  }

  public function testContentModel(): void {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = $this->container->get(EntityDisplayRepositoryInterface::class);

    $form_display = $display_repository->getFormDisplay('node', 'news');
    $this->assertFalse($form_display->isNew());
    $this->assertNull($form_display->getComponent('url_redirects'));
    $this->assertFieldsInOrder($form_display, [
      'title',
      'field_featured_image',
      'field_description',
      'field_content',
      'field_tags',
    ]);

    $default_display = $display_repository->getViewDisplay('node', 'news');
    $this->assertNull($default_display->getComponent('links'));
    $this->assertFieldsInOrder($default_display, [
      'field_featured_image',
      'content_moderation_control',
      'field_content',
      'field_tags',
    ]);
    $this->assertSharedFieldsInSameOrder($form_display, $default_display);

    $card_display = $display_repository->getViewDisplay('node', 'news', 'card');
    $this->assertNull($default_display->getComponent('links'));
    $this->assertFieldsInOrder($card_display, [
      'field_featured_image',
      'field_description',
    ]);
    $this->assertArraySubset([
      'field_featured_image' => [
        'type' => 'entity_reference_entity_view',
      ],
    ], $card_display->getComponents());

    $teaser_display = $display_repository->getViewDisplay('node', 'news', 'card');
    $this->assertNull($teaser_display->getComponent('links'));
    $this->assertFieldsInOrder($teaser_display, [
      'field_featured_image',
      'field_description',
    ]);

    $this->assertContentModel([
      'news' => [
        'title' => [
          'type' => 'string',
          'cardinality' => 1,
          'required' => TRUE,
          'translatable' => TRUE,
          'label' => 'Title',
          'input type' => 'text',
          'help text' => '',
        ],
        'field_description' => [
          'type' => 'string_long',
          'cardinality' => 1,
          'required' => TRUE,
          'translatable' => TRUE,
          'label' => 'Description',
          'input type' => 'textarea',
          'help text' => 'Describe the page content. This appears as the description in search engine results.',
        ],
        'field_featured_image' => [
          'type' => 'entity_reference',
          'cardinality' => 1,
          'required' => FALSE,
          'translatable' => FALSE,
          'label' => 'Featured image',
          'input type' => 'media library',
          'help text' => 'Include an image. This appears as the image in search engine results.',
        ],
        'field_content' => [
          'type' => 'text_long',
          'cardinality' => 1,
          'required' => FALSE,
          'translatable' => TRUE,
          'label' => 'Content',
          'input type' => 'wysiwyg',
          'help text' => 'The content of this page.',
        ],
        'field_tags' => [
          'type' => 'entity_reference',
          'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
          'required' => FALSE,
          'translatable' => FALSE,
          'label' => 'Tags',
          'input type' => 'tagify',
          'help text' => 'Include tags for relevant topics.',
        ],
      ],
    ]);
  }

  public function testPathAliasPatternPrecedence(): void {
    $dir = realpath(__DIR__ . '/../../../../drupal_cms_seo_basic');
    $this->applyRecipe($dir);

    // Confirm that news items have the expected path aliases.
    $node = $this->drupalCreateNode([
      'type' => 'news',
      'title' => 'Test News',
    ]);
    $now = date('Y-m', $node->getCreatedTime());
    $this->assertStringEndsWith("/news/$now/test-news", $node->toUrl()->toString());
  }

}
