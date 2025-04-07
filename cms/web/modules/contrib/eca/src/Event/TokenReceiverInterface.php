<?php

namespace Drupal\eca\Event;

/**
 * Interface for events that can receive tokens from the triggering action.
 */
interface TokenReceiverInterface {

  /**
   * Adds a list of token names, that should be kept when clearing token data.
   *
   * @param array $token_names
   *   The list of token names to be kept.
   *
   * @return \Drupal\eca\Event\TokenReceiverInterface
   *   This.
   *
   * @see \Drupal\eca\EventSubscriber\EcaExecutionTokenSubscriber::onBeforeInitialExecution
   * @see \Drupal\eca\Token\TokenInterface::clearTokenData
   */
  public function addTokenNamesToReceive(array $token_names): TokenReceiverInterface;

  /**
   * Helper function to receive a comma separated string of token names.
   *
   * This breaks the string into its components and cleans them all up to then
   * call ::addTokenNamesToReceive.
   *
   * @param string $token_names
   *   Comma separated list of token names to be kept.
   *
   * @return \Drupal\eca\Event\TokenReceiverInterface
   *   This.
   */
  public function addTokenNamesFromString(string $token_names): TokenReceiverInterface;

  /**
   * Return the list of token names that should be kept.
   *
   * @return array
   *   List of token names.
   */
  public function getTokenNamesToReceive(): array;

}
