<?php

namespace Drupal\eca\Event;

use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Trait to implement all required methods for the TokenReceiverInterface.
 */
trait TokenReceiverTrait {

  /**
   * List of token names to be kept.
   *
   * @var array
   */
  protected array $tokenNames = [];

  /**
   * {@inheritdoc}
   */
  public function addTokenNamesToReceive(array $token_names): TokenReceiverInterface {
    $this->tokenNames = array_unique(array_merge($this->tokenNames, $token_names));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addTokenNamesFromString(string $token_names): TokenReceiverInterface {
    $token_names_array = DataTransferObject::buildArrayFromUserInput($token_names);
    return empty($token_names_array) ? $this : $this->addTokenNamesToReceive($token_names_array);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenNamesToReceive(): array {
    return $this->tokenNames;
  }

}
