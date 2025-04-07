<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Drupal\node\Entity\NodeType;

/**
 * Provides Gherkin test steps around node entities.
 */
class Node implements Context {

  /**
   * Create one or more node bundles at the start of a test.
   *
   * Creates content types in the form:
   * | type    | name    |
   * | article | Article |
   *
   * @Given content type(s):
   */
  public function assertContentTypes(TableNode $contentTypes) : void {
    foreach ($contentTypes->getHash() as $contentType) {
      assert(isset($contentType['type']), "type must be specified");
      assert(isset($contentType['name']), "name must be specified");

      NodeType::create($contentType)->save();
    }
  }

}
