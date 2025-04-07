<?php

namespace Drupal\Tests\yoast_seo\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\yoast_seo\Traits\YoastSEOTestTrait;

/**
 * Tests the entity analyzer.
 *
 * @group yoast_seo
 */
class EntityAnalyserTest extends KernelTestBase {

  use YoastSEOTestTrait;

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }
  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }

  /**
   * The Real Time SEO Entity Analyzer.
   *
   * @var \Drupal\yoast_seo\EntityAnalyser
   */
  protected $entityAnalyzer;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'yoast_seo',
    'node',
    'datetime',
    'user',
    'system',
    'filter',
    'field',
    'text',
    'token',
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    // Set up everything needed to be able to use nodes.
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('date_format');
    $this->installConfig('system');
    $this->installConfig('filter');
    $this->installConfig('node');

    // Install the default metatag configuration which sets some presets for
    // tokens that affect how rendering happens.
    $this->installConfig('metatag');

    // Create an article content type that we will use for testing.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
      'display_submitted' => FALSE,
    ]);

    // Add our test field to the node type.
    $this->createYoastSeoField('node', 'article', 'field_seo', 'SEO');

    $this->entityAnalyzer = $this->container->get('yoast_seo.entity_analyser');
  }

  /**
   * Tests that the entity preview works with unsaved nodes.
   */
  public function testEntityPreviewWithUnsavedNode() {
    // Can't use createNode because it saves the node, which we don't want.
    $unsaved_node = Node::create([
      'type' => 'article',
      'title'     => $this->randomMachineName(8),
      'body'      => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'uid' => 0,
    ]);

    $preview_data = $this->entityAnalyzer->createEntityPreview($unsaved_node);

    $this->assertNotEmpty($preview_data['title']);
    $this->assertNotEmpty($preview_data['text']);
    $this->assertEmpty($preview_data['url']);
  }

  /**
   * Tests that the entity preview works with saved nodes.
   */
  public function testEntityPreviewWithSavedNode() {
    $node = $this->drupalCreateNode([
      'type' => 'article',
    ]);

    $preview_data = $this->entityAnalyzer->createEntityPreview($node);

    $this->assertNotEmpty($preview_data['title']);
    $this->assertNotEmpty($preview_data['text']);
    $this->assertNotEmpty($preview_data['url']);
  }

}
