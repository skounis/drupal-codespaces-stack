<?php

namespace Drupal\trash\EntityQuery\Workspaces;

use Drupal\workspaces\EntityQuery\QueryAggregate as BaseQueryAggregate;

/**
 * Ensures that aggregate entity queries invoke the alter hooks.
 */
class QueryAggregate extends BaseQueryAggregate {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this
      ->alter()
      ->prepare()
      ->addAggregate()
      ->compile()
      ->compileAggregate()
      ->addGroupBy()
      ->addSort()
      ->addSortAggregate()
      ->finish()
      ->result();
  }

}
