<?php

namespace Drupal\Tests\sitemap\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Base class for some Sitemap test cases.
 */
abstract class SitemapTaxonomyTestBase extends SitemapBrowserTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceFieldCreationTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['sitemap', 'node', 'taxonomy', 'views'];

  /**
   * A vocabulary entity.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * A string to identify the field name for testing terms.
   *
   * @var string
   */
  protected $fieldTagsName;

  /**
   * An array of taxonomy terms.
   *
   * @var array
   */
  protected $terms;

  /**
   * A user account to test with.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure the Article node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Create a vocabulary.
    $this->vocabulary = $this->createVocabulary();

    // Create user, then login.
    $this->user = $this->drupalCreateUser([
      'administer sitemap',
      'access sitemap',
      'administer nodes',
      'create article content',
      'administer taxonomy',
    ]);
    $this->drupalLogin($this->user);

    // Configure the sitemap to display the vocabulary.
    $vid = $this->vocabulary->id();
    $this->saveSitemapForm(["plugins[vocabulary:$vid][enabled]" => TRUE]);
  }

  /**
   * Create taxonomy terms.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Taxonomy vocabulary.
   *
   * @return array
   *   List of tags.
   *
   * @throws \Exception
   */
  protected function createTerms(Vocabulary $vocabulary) {
    $term0 = $this->createTerm($vocabulary);
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);
    return [$term0, $term1, $term2];
  }

  /**
   * Create taxonomy terms.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Taxonomy vocabulary.
   *
   * @return array
   *   List of tags.
   *
   * @throws \Exception
   */
  protected function createNestedTerms(Vocabulary $vocabulary) {
    $term0 = $this->createTerm($vocabulary);
    $term1 = $this->createTerm($vocabulary, ['parent' => $term0->id()]);
    $term2 = $this->createTerm($vocabulary, ['parent' => $term1->id()]);
    return [$term0, $term1, $term2];
  }

  /**
   * Create node and assign tags to it.
   *
   * @param array $terms
   *   An array of taxonomy terms to apply to the node.
   *
   * @throws \Exception
   */
  protected function createNodeWithTerms(array $terms = []) {
    if (empty($terms)) {
      $this->terms = $this->createTerms($this->vocabulary);
    }

    // Add an entity reference field to a node bundle.
    $this->addEntityreferenceField();

    $values = [];
    foreach ($terms as $term) {
      $values[] = $term->getName();
    }
    $title = $this->randomString();
    $edit = [
      'title[0][value]' => $title,
      $this->fieldTagsName . '[target_id]' => implode(',', $values),
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
  }

  /**
   * Add an entity reference field to tag nodes.
   */
  protected function addEntityreferenceField() {
    $this->fieldTagsName = 'field_' . $this->vocabulary->id();

    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];

    // Create the entity reference field for terms.
    $this->createEntityReferenceField('node', 'article', $this->fieldTagsName, 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    // Configure for autocomplete display.
    EntityFormDisplay::load('node.article.default')
      ->setComponent($this->fieldTagsName, [
        'type' => 'entity_reference_autocomplete_tags',
      ])
      ->save();
  }

}
