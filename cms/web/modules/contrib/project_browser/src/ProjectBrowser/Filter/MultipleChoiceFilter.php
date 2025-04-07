<?php

declare(strict_types=1);

namespace Drupal\project_browser\ProjectBrowser\Filter;

/**
 * Defines a filter to choose any number of options from a list.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
final class MultipleChoiceFilter extends FilterBase {

  public function __construct(
    public readonly array $choices,
    array $value,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
    $this->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(): array {
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(mixed $value): void {
    // Everything in $value must be a valid choice.
    assert(
      is_array($value) &&
      array_diff($value, array_keys($this->choices)) === []
    );
    parent::setValue($value);
  }

}
