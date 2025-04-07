<?php

namespace Drupal\eca\Token;

use Drupal\Core\Utility\Token as CoreToken;
use Drupal\token\Token;

/**
 * The ECA token service, which is a decorator for the contrib token service.
 *
 * The contrib token module replaces the core service definition by its own
 * class. By extending the token service that way, it may break existing
 * implementations that access properties and methods that are only available
 * on the contrib token service. In order to prevent such compatibility issues,
 * we let the service container use this service class when contrib token is
 * installed.
 *
 * @see \Drupal\eca\Token\TokenServices
 */
class ContribToken extends Token implements TokenInterface {

  use TokenDecoratorTrait;

  /**
   * The decorated token service.
   *
   * @var \Drupal\token\Token
   */
  protected CoreToken $token;

  /**
   * {@inheritdoc}
   */
  public function getTypeInfo($token_type) {
    return $this->token->getTypeInfo($token_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenInfo($token_type, $token) {
    return $this->token->getTokenInfo($token_type, $token);
  }

  /**
   * {@inheritdoc}
   */
  public function getGlobalTokenTypes(): array {
    return $this->token->getGlobalTokenTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function getInvalidTokens($type, $tokens): array {
    return $this->token->getInvalidTokens($type, $tokens);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvalidTokensByContext($value, array $valid_types = []): array {
    return $this->token->getInvalidTokensByContext($value, $valid_types);
  }

}
