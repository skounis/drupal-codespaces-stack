<?php

namespace Drupal\Tests\eca\Kernel\Model;

/**
 * Model test for saving a new entity.
 *
 * @group eca
 * @group eca_model
 */
class SaveNewEntityTest extends Base {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'text',
    'eca_base',
    'eca_content',
    'eca_test_model_save_new_entity',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->switchUser(1);
  }

  /**
   * Tests the saving of a new entity.
   */
  public function testSaveNewEntity(): void {
    $etm = \Drupal::entityTypeManager();
    $node_storage = $etm->getStorage('node');
    $term_storage = $etm->getStorage('taxonomy_term');

    $title = $this->randomMachineName();
    $article = $node_storage->create([
      'type' => 'article',
      'title' => $title,
      'body' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $article->save();
    $article = $node_storage->load($article->id());

    $this->assertEquals($title, (string) $article->label(), 'Article title must remain unchanged.');
    $tag_terms = $term_storage->loadByProperties(['name' => 'Tag: ' . $title]);
    $this->assertCount(1, $tag_terms, 'Exactly one tag term must have been created whose name is "Tag: [article:title]".');
    $tag_term = reset($tag_terms);
    $this->assertIsObject($tag_term, 'A tag term must have been created whose name is "Tag: [article:title]".');
    $this->assertEquals('Tag: ' . $title, (string) $tag_term->label(), 'The loaded tag term returns the exact same label as requested for the load.');
    $this->assertEquals('tags', $tag_term->bundle(), 'The tag term must be of vocabulary "tags".');
  }

}
