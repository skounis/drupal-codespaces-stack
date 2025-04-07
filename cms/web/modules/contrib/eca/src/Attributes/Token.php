<?php

declare(strict_types=1);

namespace Drupal\eca\Attributes;

/**
 * Provides the token attribute for ECA token providers.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Token {

  /**
   * Constructor for the ECA token attribute.
   *
   * @param string $name
   *   The token name.
   * @param string $description
   *   A one line description.
   * @param string[] $classes
   *   The list of event classes that provide that token. Leave empty if all
   *   derivations of the same base plugin are supporting that token.
   * @param \Drupal\eca\Attributes\Token[] $properties
   *   The list of optional token properties.
   * @param string[] $aliases
   *   The list of optional token name aliases.
   */
  public function __construct(
    public string $name,
    public string $description,
    public array $classes = [],
    public array $properties = [],
    public array $aliases = [],
  ) {
  }

}
