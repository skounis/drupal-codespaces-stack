<?php

namespace Drupal\Tests\scheduler\Traits;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Additional setup trait for Scheduler tests that use Taxonomy.
 *
 * This builds on the standard SchedulerSetupTrait.
 */
trait SchedulerTaxonomyTermSetupTrait {

  /**
   * The internal name of the standard taxonomy vocabulary created for testing.
   *
   * @var string
   */
  protected $vocabularyId = 'test_vocab';

  /**
   * The readable name of the standard media type created for testing.
   *
   * @var string
   */
  protected $vocabularyName = 'Test Vocabulary';

  /**
   * The media type object which is enabled for scheduling.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The internal name of the media type not enabled for scheduling.
   *
   * @var string
   */
  protected $nonSchedulerVocabularyId = 'vocab_not_enabled';

  /**
   * The readable label of the media type not enabled for scheduling.
   *
   * @var string
   */
  protected $nonSchedulerVocabularyName = 'Test Vocabulary - not for scheduling';

  /**
   * The media type object which is not enabled for scheduling.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $nonSchedulerVocabulary;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $taxonomyTermStorage;

  /**
   * Set common properties, define content types and create users.
   */
  public function schedulerTaxonomyTermSetUp() {

    // Create a test vocabulary that is enabled for scheduling.
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $this->vocabulary = Vocabulary::create([
      'vid' => $this->vocabularyId,
      'name' => $this->vocabularyName,
    ]);
    $this->vocabulary->save();

    // Add scheduler functionality to the vocabulary.
    $this->vocabulary->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // Enable the scheduler fields in the default form display, mimicking what
    // would be done if the entity bundle had been enabled via admin UI.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('taxonomy_term', $this->vocabularyId)
      ->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default'])
      ->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default'])
      ->save();

    // Create a vocabulary which is not enabled for scheduling.
    /** @var \Drupal\taxonomy\VocabularyInterface $nonSchedulerVocabulary */
    $this->nonSchedulerVocabulary = Vocabulary::create([
      'vid' => $this->nonSchedulerVocabularyId,
      'name' => $this->nonSchedulerVocabularyName,
    ]);
    $this->nonSchedulerVocabulary->save();

    /** @var  taxonomyTermStorage $taxonomyTermStorage */
    $this->taxonomyTermStorage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');

    // Add extra permissions to the role assigned to the adminUser.
    $this->addPermissionsToUser($this->adminUser, [
      'create terms in ' . $this->vocabularyId,
      'edit terms in ' . $this->vocabularyId,
      'delete terms in ' . $this->vocabularyId,
      'create terms in ' . $this->nonSchedulerVocabularyId,
      'edit terms in ' . $this->nonSchedulerVocabularyId,
      'administer taxonomy',
      'access taxonomy overview',
      'schedule publishing of taxonomy_term',
      'view scheduled taxonomy_term',
    ]);

    // Add extra permissions to the role assigned to the schedulerUser.
    $this->addPermissionsToUser($this->schedulerUser, [
      'create terms in ' . $this->vocabularyId,
      'edit terms in ' . $this->vocabularyId,
      'schedule publishing of taxonomy_term',
    ]);

  }

  /**
   * Creates a taxonomy term.
   *
   * @param array $values
   *   The values to use for the entity.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The created taxonomy term object.
   */
  public function createTaxonomyTerm(array $values) {
    // Provide defaults for the critical values.
    $values += [
      'vid' => $this->vocabularyId,
      // If no name is specified then use title, or default to a random name.
      'name' => $values['title'] ?? $this->randomMachineName(),
    ];
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = Term::create($values);
    $term->save();
    return $term;
  }

  /**
   * Gets a taxonomy term from storage.
   *
   * @param string $name
   *   Optional name text to match on. If given and no match, returns NULL.
   *   If no $name is given then returns the term with the highest id value.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The taxonomy term object.
   */
  public function getTaxonomyTerm(?string $name = NULL) {
    $query = $this->taxonomyTermStorage->getQuery()
      ->accessCheck(FALSE)
      ->sort('tid', 'DESC');
    if (!empty($name)) {
      $query->condition('name', $name);
    }
    $result = $query->execute();
    if (count($result)) {
      $term_id = reset($result);
      return $this->taxonomyTermStorage->load($term_id);
    }
    else {
      return NULL;
    }
  }

}
