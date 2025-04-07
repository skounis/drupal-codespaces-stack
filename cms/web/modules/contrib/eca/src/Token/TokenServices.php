<?php

namespace Drupal\eca\Token;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;

/**
 * Wrapper class for ECA-related token services.
 *
 * This is an intermediary that should be used when working with token aliases
 * and when adding token data on runtime (TokenInterface::addTokenData()).
 * The reason why this wrapper exists, is because we use the decorator pattern
 * to extend the existing 'token' service. By going this extension path, we
 * want to make sure, that we are always able to make use of our method, and
 * still allow other modules to also extend the token service any further with
 * their own decorators.
 */
class TokenServices implements TokenInterface {

  /**
   * The ECA-specific decorator instance of the token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $decorator;

  /**
   * The final instance of the token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The TokenServices constructor.
   *
   * @param \Drupal\eca\Token\TokenInterface $eca_decorator
   *   The ECA-specific decorator instance of the token service.
   * @param \Drupal\Core\Utility\Token $token
   *   The final instance of the token service.
   */
  public function __construct(TokenInterface $eca_decorator, Token $token) {
    $this->decorator = $eca_decorator;
    $this->token = $token;
  }

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\Token\TokenInterface
   *   The Token services.
   */
  public static function get(): TokenInterface {
    return \Drupal::service('eca.token_services');
  }

  /**
   * {@inheritdoc}
   */
  public function getDataProviders(): array {
    return $this->decorator->getDataProviders();
  }

  /**
   * {@inheritdoc}
   */
  public function addTokenData(string $key, $data): TokenInterface {
    $this->decorator->addTokenData($key, $data);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addTokenDataProvider(DataProviderInterface $provider): TokenInterface {
    $this->decorator->addTokenDataProvider($provider);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTokenDataProvider(DataProviderInterface $provider): TokenInterface {
    $this->decorator->removeTokenDataProvider($provider);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTokenData(?string $key = NULL): bool {
    return $this->decorator->hasTokenData($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenData(?string $key = NULL) {
    return $this->decorator->getTokenData($key);
  }

  /**
   * {@inheritdoc}
   */
  public function clearTokenData(): void {
    $this->decorator->clearTokenData();
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType($value): ?string {
    return $this->decorator->getTokenType($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenTypeForEntityType(string $entity_type_id): ?string {
    return $this->decorator->getTokenTypeForEntityType($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeForTokenType(string $token_type): ?string {
    return $this->decorator->getEntityTypeForTokenType($token_type);
  }

  /**
   * {@inheritdoc}
   */
  public function generate($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    return $this->token->generate($type, $tokens, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function replace($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return $this->token->replace($text, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function replaceClear($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $options['clear'] = TRUE;
    return $this->replace($text, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function replacePlain(string $plain, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    return $this->token->replacePlain($plain, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrReplace($text, array $data = [], ?array $options = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return $this->decorator->getOrReplace($text, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function scan($text) {
    return $this->token->scan($text);
  }

  /**
   * {@inheritdoc}
   */
  public function scanRootLevelTokens($text): array {
    return $this->decorator->scanRootLevelTokens($text);
  }

  /**
   * {@inheritdoc}
   */
  public function findWithPrefix(array $tokens, $prefix, $delimiter = ':') {
    return $this->token->findWithPrefix($tokens, $prefix, $delimiter);
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return $this->token->getInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function setInfo(array $tokens): void {
    $this->token->setInfo($tokens);
  }

  /**
   * {@inheritdoc}
   */
  public function resetInfo(): void {
    $this->token->resetInfo();
  }

}
