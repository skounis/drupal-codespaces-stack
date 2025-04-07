<?php

declare(strict_types=1);

namespace Drupal\project_browser\ProjectBrowser\Filter;

/**
 * Defines a filter that matches some text.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
final class TextFilter extends FilterBase {

  public function __construct(string $value, mixed ...$arguments) {
    parent::__construct(...$arguments);
    $this->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(): string {
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(mixed $value): void {
    assert(is_string($value));
    parent::setValue($value);
  }

}
