<?php

declare(strict_types=1);

namespace Drupal\project_browser\ProjectBrowser\Filter;

/**
 * A base class for all filters that can be defined by source plugins.
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can be
 *   safely relied upon.
 */
abstract class FilterBase implements \JsonSerializable {

  /**
   * The filter's current value.
   *
   * @var mixed
   */
  protected mixed $value = NULL;

  public function __construct(
    public readonly string|\Stringable $name,
  ) {}

  /**
   * Returns the current filter value.
   *
   * @return mixed
   *   The current value of this filter.
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * Sets the filter's value.
   *
   * @param mixed $value
   *   The value to set.
   */
  public function setValue(mixed $value): void {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  final public function jsonSerialize(): array {
    $values = [
      '_type' => match (static::class) {
        BooleanFilter::class => 'boolean',
        MultipleChoiceFilter::class => 'multiple_choice',
        TextFilter::class => 'text',
        default => throw new \UnhandledMatchError("Unknown filter type."),
      },
    ] + get_object_vars($this);

    return array_map(
      fn ($value) => $value instanceof \Stringable ? (string) $value : $value,
      $values,
    );
  }

}
