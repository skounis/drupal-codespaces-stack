<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides Gherkin test steps around taxonomy term entities.
 */
class Term implements Context {

  /**
   * Create one or more taxonomy vocabularies at the start of a test.
   *
   * Creates taxonomy types in the form:
   * | vid      | name     |
   * | category | Category |
   *
   * @Given taxonomy type(s):
   */
  public function assertTaxonomyTypes(TableNode $taxonomyTypes) : void {
    foreach ($taxonomyTypes->getHash() as $taxonomyType) {
      assert(isset($taxonomyType['vid']), "vid must be specified");
      assert(isset($taxonomyType['name']), "name must be specified");

      Vocabulary::create($taxonomyType)->save();
    }
  }

}
