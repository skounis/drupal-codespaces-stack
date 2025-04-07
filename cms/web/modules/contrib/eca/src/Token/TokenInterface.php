<?php

namespace Drupal\eca\Token;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Interface for ECA-specific token service implementations.
 */
interface TokenInterface {

  /**
   * Gets the list of data providers.
   *
   * @return \Drupal\eca\Token\DataProviderInterface[]
   *   The list of data providers.
   */
  public function getDataProviders(): array;

  /**
   * Add token data on runtime for subsequent text replacements.
   *
   * @param string $key
   *   The data type key, e.g. 'node', 'user'. You may also use an alias, e.g.
   *   'article' or 'author' if given $data is an entity, scalar or typed data.
   *   Chained keys like 'node:article' are possible, those will be wrapped as
   *   Data Transfer Objects (DTOs) and can be accessed later on the parent key
   *   level (in this example, 'node' is the parent key and it would be treated
   *   as a DTO). A DTO can also be used as a list, items may be dynamically
   *   added by using '+' and removed by using '-'.
   *   Example: ::addTokenData('todo:+', 'nothing').
   * @param mixed $data
   *   The data value, e.g. a node entity for 'node' or alias 'article'.
   *
   * @return $this
   *   The token service itself.
   *
   * @see \Drupal\eca\Plugin\DataType\DataTransferObject
   */
  public function addTokenData(string $key, $data): TokenInterface;

  /**
   * Add a provider for getting Token data.
   *
   * This method could be used when a component holds its own set of data that
   * may be used within the Token system. For example, an external key-value
   * store could be used as its own data provider. Once added, the Token service
   * will ask data providers for existent data, using the order they were added.
   *
   * @param \Drupal\eca\Token\DataProviderInterface $provider
   *   The data provider to add.
   *
   * @return $this
   *   The token service itself.
   */
  public function addTokenDataProvider(DataProviderInterface $provider): TokenInterface;

  /**
   * Remove a previously added Token data provider.
   *
   * @param \Drupal\eca\Token\DataProviderInterface $provider
   *   The data provider to remove.
   *
   * @return $this
   *   The token service itself.
   */
  public function removeTokenDataProvider(DataProviderInterface $provider): TokenInterface;

  /**
   * Determines whether token data exists.
   *
   * @param string|null $key
   *   (optional) Use this argument to check whether data exists for a
   *   specific key.
   *
   * @return bool
   *   Returns TRUE if data exists, FALSE otherwise.
   */
  public function hasTokenData(?string $key = NULL): bool;

  /**
   * Get a data value from the token data array.
   *
   * @param string|null $key
   *   The data key hat is expected to hold the data value. Skip or set NULL
   *   to get the whole data array.
   *
   * @return mixed
   *   If the key exists in the data array, the associated data value is being
   *   returned. Otherwise, if it not exists, NULL will be returned. If the $key
   *   argument is NULL, this method returns the array of currently hold data.
   */
  public function getTokenData(?string $key = NULL);

  /**
   * Clears the list of any previously set token data.
   */
  public function clearTokenData(): void;

  /**
   * Gets the token type of the given data value if possible.
   *
   * @param mixed $value
   *   Data value for which to determine the token type.
   *
   * @return string|null
   *   The token type if available, NULL otherwise.
   */
  public function getTokenType($value): ?string;

  /**
   * Get the token type for the given entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string|null
   *   The token type or NULL if the entity type does not map to a token type.
   */
  public function getTokenTypeForEntityType(string $entity_type_id): ?string;

  /**
   * Get the entity type ID for the given token type.
   *
   * @param string $token_type
   *   The token type.
   *
   * @return string|null
   *   The entity type ID, or NULL if the token type does not map to an entity
   *   type.
   */
  public function getEntityTypeForTokenType(string $token_type): ?string;

  /**
   * Generates replacement values for a list of tokens.
   *
   * @param string $type
   *   The type of token being replaced. 'node', 'user', and 'date' are common.
   * @param array $tokens
   *   An array of tokens to be replaced, keyed by the literal text of the token
   *   as it appeared in the source text.
   * @param array $data
   *   An array of keyed objects. For simple replacement scenarios: 'node',
   *   'user', and others are common keys, with an accompanying node or user
   *   object being the value. Some token types, like 'site', do not require
   *   any explicit information from $data and can be replaced even if it is
   *   empty.
   * @param array $options
   *   A keyed array of settings and flags to control the token replacement
   *   process. Supported options are:
   *   - langcode: A language code to be used when generating locale-sensitive
   *     tokens.
   *   - callback: A callback function that will be used to post-process the
   *     array of token replacements after they are generated. Can be used when
   *     modules require special formatting of token text, for example URL
   *     encoding or truncation to a specific length.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata. This is passed to the token replacement
   *   implementations so that they can attach their metadata.
   *
   * @return array
   *   An associative array of replacement values, keyed by the original 'raw'
   *   tokens that were found in the source text. For example:
   *   $results['[node:title]'] = 'My new node';
   *
   * @see hook_tokens()
   * @see hook_tokens_alter()
   * @see \Drupal\Core\Utility\Token
   */
  public function generate($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata);

  /**
   * Replaces all tokens in a given string with appropriate values.
   *
   * @param string $text
   *   An HTML string containing replaceable tokens. The caller is responsible
   *   for calling \Drupal\Component\Utility\Html::escape() in case the $text
   *   was plain text.
   * @param array $data
   *   (optional) An array of keyed objects. For simple replacement scenarios
   *   'node', 'user', and others are common keys, with an accompanying node or
   *   user object being the value. Some token types, like 'site', do not
   *   require any explicit information from $data and can be replaced even if
   *   it is empty.
   * @param array $options
   *   (optional) A keyed array of settings and flags to control the token
   *   replacement process. Supported options are:
   *   - langcode: A language code to be used when generating locale-sensitive
   *     tokens.
   *   - callback: A callback function that will be used to post-process the
   *     array of token replacements after they are generated.
   *   - clear: A boolean flag indicating that tokens should be removed from the
   *     final text if no replacement value can be generated.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   (optional) An object to which static::generate() and the hooks and
   *   functions that it invokes will add their required bubbleable metadata.
   *
   *   To ensure that the metadata associated with the token replacements gets
   *   attached to the same render array that contains the token-replaced text,
   *   callers of this method are encouraged to pass in a BubbleableMetadata
   *   object and apply it to the corresponding render array. For example:
   *   @code
   *     $bubbleable_metadata = new BubbleableMetadata();
   *     $build['#markup'] = $token_service->replace('Tokens: [node:nid] [current_user:uid]', ['node' => $node], [], $bubbleable_metadata);
   *     $bubbleable_metadata->applyTo($build);
   *   @endcode
   *
   *   When the caller does not pass in a BubbleableMetadata object, this
   *   method creates a local one, and applies the collected metadata to the
   *   Renderer's currently active render context.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The token result is the entered HTML text with tokens replaced. The
   *   caller is responsible for choosing the right escaping / sanitization. If
   *   the result is intended to be used as plain text, using
   *   PlainTextOutput::renderFromHtml() is recommended. If the result is just
   *   printed as part of a template relying on Twig autoescaping is possible,
   *   otherwise for example the result can be put into #markup, in which case
   *   it would be sanitized by Xss::filterAdmin().
   *
   * @see \Drupal\Core\Utility\Token
   */
  public function replace($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL);

  /**
   * Same as ::replace() but automatically enables the clear option.
   *
   * The clear option indicates that tokens should be removed from the final
   * text if no replacement value can be generated.
   *
   * @param string $text
   *   See description in ::replace().
   * @param array $data
   *   See description in ::replace().
   * @param array $options
   *   See description in ::replace().
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   See description in ::replace().
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   See description in ::replace().
   *
   * @see ::replace()
   */
  public function replaceClear($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL);

  /**
   * Replaces all tokens in a given plain text string with appropriate values.
   *
   * @param string $plain
   *   Plain text string.
   * @param array $data
   *   (optional) An array of keyed objects. See replace().
   * @param array $options
   *   (optional) A keyed array of options. See replace().
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   (optional) Target for adding metadata. See replace().
   *
   * @return string
   *   The entered plain text with tokens replaced.
   */
  public function replacePlain(string $plain, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL): string;

  /**
   * Returns data when text matches with a data key or runs string replacement.
   *
   * Some components may allow direct usage of data, if it is being addressed
   * with an existing data key. For example, when the $text argument is "[node]"
   * and the Token service has a node entity available, that is keyed with
   * "node", then this method returns that node entity. If however, the $text
   * argument would be "A text using [node:title] stuff", then this argument is
   * not treated for direct data access - instead this method passes the string
   * through regular token replacement and returns the replacement result.
   *
   * @param string $text
   *   See description in ::replace().
   * @param array $data
   *   See description in ::replace().
   * @param array|null $options
   *   See description in ::replace(). If not specified otherwise, the "clear"
   *   option is set to TRUE, which is the equivalent to ::replaceClear().
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   See description in ::replace().
   *
   * @return mixed
   *   Either the data or the passed text, replaced with tokens.
   *
   * @see ::getTokenData()
   * @see ::replace()
   */
  public function getOrReplace($text, array $data = [], ?array $options = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL);

  /**
   * Builds a list of all token-like patterns that appear in the text.
   *
   * @param string $text
   *   The text to be scanned for possible tokens.
   *
   * @return array
   *   An associative array of discovered tokens, grouped by type.
   *
   * @see \Drupal\Core\Utility\Token
   */
  public function scan($text);

  /**
   * Scans the text for root-level tokens (Tokens without further keys).
   *
   * Tokens usually consist of two parts: the type and a name. We allow
   * users to set Tokens without specifying any of these, for example [list].
   * Therefore extra work is needed to support this scheme.
   *
   * Root-level tokens will be converted into "real" tokens by prepending
   * an explicit token type "_eca_root_token" that will then be properly
   * handled by the rest of the Token string replacement pipeline.
   *
   * @param mixed $text
   *   The text to scan.
   *
   * @return array
   *   An associative array of discovered root-level tokens, grouped by type.
   */
  public function scanRootLevelTokens($text): array;

  /**
   * Returns a list of tokens that begin with a specific prefix.
   *
   * Used to extract a group of 'chained' tokens (such as [node:author:name])
   * from the full list of tokens found in text. For example:
   * @code
   *   $data = [
   *     'author:name' => '[node:author:name]',
   *     'title'       => '[node:title]',
   *     'created'     => '[node:created]',
   *   ];
   *   $results = Token::findWithPrefix($data, 'author');
   *   $results == ['name' => '[node:author:name]'];
   * @endcode
   *
   * @param array $tokens
   *   A keyed array of tokens, and their original raw form in the source text.
   * @param string $prefix
   *   A textual string to be matched at the beginning of the token.
   * @param string $delimiter
   *   (optional) A string containing the character that separates the prefix
   *   from the rest of the token. Defaults to ':'.
   *
   * @return array
   *   An associative array of discovered tokens, with the prefix and delimiter
   *   stripped from the key.
   *
   * @see \Drupal\Core\Utility\Token
   */
  public function findWithPrefix(array $tokens, $prefix, $delimiter = ':');

  /**
   * Returns metadata describing supported tokens.
   *
   * The metadata array contains token type, name, and description data as well
   * as an optional pointer indicating that the token chains to another set of
   * tokens.
   *
   * @return array
   *   An associative array of token information, grouped by token type. The
   *   array structure is identical to that of hook_token_info().
   *
   * @see hook_token_info()
   * @see \Drupal\Core\Utility\Token
   */
  public function getInfo(): array;

  /**
   * Sets metadata describing supported tokens.
   *
   * @param array $tokens
   *   Token metadata that has an identical structure to the return value of
   *   hook_token_info().
   *
   * @see hook_token_info()
   */
  public function setInfo(array $tokens): void;

  /**
   * Resets metadata describing supported tokens.
   *
   * @see \Drupal\Core\Utility\Token
   */
  public function resetInfo(): void;

}
