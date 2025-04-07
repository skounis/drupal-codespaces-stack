<?php

declare(strict_types=1);

namespace Drupal\project_browser\ProjectBrowser\Filter;

/**
 * Defines a filter that can either be on, or off.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
final class BooleanFilter extends FilterBase {

  public function __construct(bool $value, mixed ...$arguments) {
    parent::__construct(...$arguments);
    $this->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(): bool {
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(mixed $value): void {
    // We're willing to convert a numeric value to boolean.
    assert(is_bool($value) || is_numeric($value));
    parent::setValue((bool) $value);
  }

}
