<?php

namespace Drupal\eca\Token;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Utility\Token;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\TokenGenerateEvent;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A trait for ECA-specific token service decorators.
 *
 * The token service is being extended this way to support collection of data
 * which may be added on runtime, plus it allows the usage of aliases in order
 * to use multiple data sets of the same type, e.g. you can use both the
 * currently logged in user and the author user of a node to replace values
 * in a given text.
 *
 * @see \Drupal\eca\Token\TokenInterface
 */
trait TokenDecoratorTrait {

  /**
   * An array of currently hold token data.
   *
   * @var array
   */
  protected array $data = [];

  /**
   * A list of Token data providers.
   *
   * @var \Drupal\eca\Token\DataProviderInterface[]
   */
  protected array $dataProviders = [];

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The current recursion level.
   *
   * @var int
   */
  protected int $recursionLevel = 0;

  /**
   * {@inheritdoc}
   */
  public function getDataProviders(): array {
    return $this->dataProviders;
  }

  /**
   * Set the token service that is being decorated by this service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service to decorate.
   */
  public function setDecoratedToken(Token $token): void {
    // @phpstan-ignore-next-line
    $this->token = $token;
  }

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher): void {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function addTokenData(string $key, $data): TokenInterface {
    $key = $this->normalizeKey($key);
    $parts = explode(':', $key);
    $key = array_shift($parts);

    if (is_string($data) && (trim($data) === '')) {
      // Treat an empty string like a NULL value. This mostly happens when
      // an empty value is used to set a Token, in order to remove a previously
      // set value.
      $data = NULL;
    }

    // Directly use the entity, instead of the wrapped adapter.
    if ($data instanceof EntityAdapter) {
      $data = $data->getEntity();
    }

    if (empty($parts) && (is_null($data) || $this->getTokenType($data))) {
      $this->data[$key] = $data;
      return $this;
    }

    // Either there is no known token type available, or the given key is a
    // chained token. For both cases, wrap the data as Data Transfer Object.
    if ($this->hasTokenData($key)) {
      $current_data = $this->getTokenData($key);
      $dto = $current_data instanceof DataTransferObject ? $current_data : DataTransferObject::create($current_data);
    }
    else {
      $dto = DataTransferObject::create();
    }
    if ($this->getTokenData($key) !== $dto) {
      $this->addTokenData($key, $dto);
    }
    while ($parts) {
      $key = array_shift($parts);
      if (!$parts) {
        $dto->set($key, $data);
        return $this;
      }
      if (!isset($dto->$key)) {
        $dto->set($key, DataTransferObject::create(NULL, $dto, $key));
      }
      elseif (!($dto->get($key) instanceof DataTransferObject)) {
        $dto->set($key, DataTransferObject::create($dto->get($key)->getValue(), $dto, $key));
      }
      /**
       * @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto
       */
      $dto = $dto->get($key);
    }
    if (is_scalar($data)) {
      $dto->setStringRepresentation($data);
    }
    else {
      $dto->setValue($data);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addTokenDataProvider(DataProviderInterface $provider): TokenInterface {
    if (!in_array($provider, $this->dataProviders, TRUE)) {
      $this->dataProviders[] = $provider;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTokenDataProvider(DataProviderInterface $provider): TokenInterface {
    $this->dataProviders = array_filter($this->dataProviders, static function ($added) use ($provider) {
      return $added !== $provider;
    });
    return $this;
  }

  /**
   * Gets the token type of the given data value if possible.
   *
   * @param mixed $value
   *   Data value for which to determine the token type.
   *
   * @return string|null
   *   The token type if available, NULL otherwise.
   */
  public function getTokenType($value): ?string {
    $tokenType = NULL;
    if ($value instanceof EntityInterface) {
      $tokenType = $this->getTokenTypeForEntityType($value->getEntityTypeId());
    }
    elseif ($value instanceof DataTransferObject) {
      $tokenType = 'dto';
    }
    return $tokenType;
  }

  /**
   * Get the token type for the given entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string|null
   *   The token type or NULL if the entity type does not map to a token type.
   */
  public function getTokenTypeForEntityType(string $entity_type_id): ?string {
    $tokenType = NULL;
    if (\Drupal::hasService('token.entity_mapper')) {
      /**
       * @var \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
       */
      $token_entity_mapper = \Drupal::service('token.entity_mapper');
      $tokenType = $token_entity_mapper->getTokenTypeForEntityType($entity_type_id, TRUE);
    }
    elseif ($definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id, FALSE)) {
      $tokenType = $definition->get('token_type') ?: $entity_type_id;
    }
    return $tokenType;
  }

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
  public function getEntityTypeForTokenType(string $token_type): ?string {
    $entity_type_id = NULL;
    if (\Drupal::hasService('token.entity_mapper')) {
      /**
       * @var \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
       */
      $token_entity_mapper = \Drupal::service('token.entity_mapper');
      $entity_type_id = $token_entity_mapper->getEntityTypeForTokenType($token_type) ?: NULL;
    }
    else {
      $entity_type_manager = \Drupal::entityTypeManager();
      if ($entity_type_manager->hasDefinition($token_type)) {
        $entity_type_id = $token_type;
      }
      // Special handling for taxonomy.
      elseif (in_array($token_type, ['term', 'vocabulary'])) {
        $entity_type_id = 'taxonomy_' . $token_type;
      }
      // Go the painful road of looking at every type definition.
      else {
        foreach ($entity_type_manager->getDefinitions() as $plugin_id => $definition) {
          if ($token_type === $definition->get('token_type')) {
            $entity_type_id = $plugin_id;
            break;
          }
        }
      }
    }
    return $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTokenData(?string $key = NULL): bool {
    if (isset($key)) {
      return !is_null($this->getTokenData($key));
    }
    return !empty($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenData(?string $key = NULL) {
    if (!isset($key)) {
      return $this->data;
    }

    $key = $this->normalizeKey($key);
    $parts = explode(':', $key);
    $key = array_shift($parts);
    $data = NULL;
    if (isset($this->data[$key])) {
      $data = $this->data[$key];
    }
    elseif (!empty($this->dataProviders)) {
      foreach ($this->dataProviders as $provider) {
        if ($provider->hasData($key)) {
          $data = $provider->getData($key);
          if ($data instanceof EntityAdapter) {
            $data = $data->getEntity();
          }
          break;
        }
      }
    }
    foreach ($parts as $partKey) {
      if (!is_object($data) || !isset($data->$partKey)) {
        return NULL;
      }
      if ($data instanceof EntityInterface || $data instanceof ComplexDataInterface) {
        $data = $data->get($partKey);
      }
      else {
        $data = $data->$partKey;
      }
    }
    if ($data instanceof TypedDataInterface && $data->getValue() instanceof EntityInterface) {
      $data = $data->getValue();
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function clearTokenData(): void {
    $this->data = [];
  }

  /**
   * {@inheritdoc}
   */
  public function generate($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $this->recursionLevel++;

    if ($type === '_eca_root_token') {
      // Check for each item when generating values for root-level tokens.
      foreach (array_keys($tokens) as $root_level_token) {
        if (!isset($data[$root_level_token]) && $this->hasTokenData($root_level_token)) {
          // Use previously set data in case it's not given otherwise.
          $data[$root_level_token] = $this->getTokenData($root_level_token);
        }
      }
    }
    elseif (!isset($data[$type]) && $this->hasTokenData($type)) {
      // Use previously set data in case it's not given otherwise.
      $data[$type] = $this->getTokenData($type);
    }

    if (1 === $this->recursionLevel) {
      foreach ($tokens as $name => $original) {
        $token_event = new TokenGenerateEvent($type, $name, $original, $data, $options, $bubbleable_metadata);
        $this->eventDispatcher->dispatch($token_event, EcaEvents::TOKEN);
        $data = $token_event->getData() + $data;
      }
    }

    if (isset($data[$type])) {
      $hold_token_data = $data[$type];
      $real_token_type = $this->getTokenType($hold_token_data);

      // The following if-block is a band-aid for menu links when contrib Token
      // is installed, as it wrongly makes use of the menu link content entity
      // instead of its according plugin.
      // @todo Remove this block once #3314427 got fixed.
      // @see https://www.drupal.org/project/token/issues/3314427
      // @see https://www.drupal.org/project/eca/issues/3314123
      if ($type === 'menu-link') {
        $real_token_type = 'menu-link';
      }

      // The token contrib module defines the book token type which is an alias
      // for the node token type. We need to keep the alias, otherwise tokens
      // would break.
      // @see https://www.drupal.org/project/eca/issues/3340582
      if ($type === 'book') {
        $real_token_type = 'book';
      }

      // The webform_access contrib module defines the webform_access token type
      // which is an alias for the node token type. We need to keep the alias,
      // otherwise tokens would break.
      // @see https://www.drupal.org/project/eca/issues/3378283
      if ($type === 'webform_access') {
        $real_token_type = 'webform_access';
      }

      // Check whether we hold aliased Token data. Exclude the alias mapping if
      // the "token_type" key is set, which comes from the contrib Token module
      // and is set within the scope of generic entity tokens. Otherwise, since
      // we are also using "entity" as an alias - without checking for that key
      // this method would cause an infinite loop.
      // @todo Find a more reliable way to prevent infinite recursion.
      if (isset($real_token_type) && $real_token_type !== $type && !isset($data['token_type'])) {
        // Given $type argument is an alias, thus use its mapped token type.
        $alias = $type;
        $type = $real_token_type;
        $data[$type] = $hold_token_data;
        unset($data[$alias]);
      }
    }

    // Now that we have mapped a possibly given alias to its type, we can let
    // the decorated token service do its original job (again). That passthrough
    // will not overwrite any other aliased data, because the returned token
    // replacements are keyed by their "raw" original token input, and that
    // always includes the alias as a prefix.
    $replacements = $this->token->generate($type, $tokens, $data, $options, $bubbleable_metadata);
    $this->recursionLevel--;
    return $replacements;
  }

  /**
   * {@inheritdoc}
   */
  public function replace($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // Replacement of aliased tokens can only work within the scope of this
    // decorator. Thus we call it on its own.
    $text = parent::replace($text, $data, $options, $bubbleable_metadata);

    // Either the class of this decorator inherits from the Core token service
    // or from the Contrib token service (if available). Just in case we
    // actually received a decorated service that differs from these two
    // implementation variants, give it a chance to execute its own logic.
    if (!in_array(get_class($this->token), [
      'Drupal\Core\Utility\Token',
      'Drupal\token\Token',
    ], TRUE)) {
      $text = $this->token->replace($text, $data, $options, $bubbleable_metadata);
    }
    return $text;
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
   *
   * Logical-wise, this behaves the same as ::replace(). See the comments there.
   */
  public function replacePlain(string $plain, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    $plain = parent::replacePlain($plain, $data, $options, $bubbleable_metadata);

    if (!in_array(get_class($this->token), [
      'Drupal\Core\Utility\Token',
      'Drupal\token\Token',
    ], TRUE) && method_exists($this->token, 'replacePlain')) {
      $plain = $this->token->replacePlain($plain, $data, $options, $bubbleable_metadata);
    }
    return $plain;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrReplace($text, array $data = [], ?array $options = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $string = (string) $text;
    if ((mb_substr($string, 0, 1) === '[') && (mb_substr($string, -1, 1) === ']') && (mb_strlen($string) <= 255)) {
      $string = mb_substr($string, 1, -1);
      if (!empty($data) && ($value = NestedArray::getValue($data, explode(':', $string)))) {
        return $value;
      }
      if ($this->hasTokenData($string)) {
        return $this->getTokenData($string);
      }
    }
    return isset($options) ? $this->replace($text, $data, $options, $bubbleable_metadata) : $this->replaceClear($text, $data, [], $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function scan($text) {
    return $this->token->scan($text) + $this->scanRootLevelTokens($text);
  }

  /**
   * {@inheritdoc}
   */
  public function scanRootLevelTokens($text): array {
    preg_match_all('/
      \[             # [ - pattern start
      ([^\s\[\]:]+)  # match $type not containing whitespace : [ or ]
      \]             # ] - pattern end
      /x', $text, $matches);

    $tokens = $matches[1];

    $results = [];
    $tokenCount = count($tokens);
    for ($i = 0; $i < $tokenCount; $i++) {
      $results['_eca_root_token'][$tokens[$i]] = $matches[0][$i];
    }

    return $results;
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

  /**
   * Normalizes the given key that may be provided by user input.
   *
   * @param string $key
   *   The key to normalize.
   *
   * @return string
   *   The normalized key.
   */
  protected function normalizeKey(string $key): string {
    $key = mb_strtolower(trim($key));

    if (!empty($key)) {
      if ((mb_substr($key, 0, 1) === '[') && (mb_substr($key, -1, 1) === ']')) {
        // Remove the brackets coming from Token syntax.
        $key = mb_substr($key, 1, -1);
      }
      if (mb_strpos($key, '.')) {
        // User input may use "." instead of ":".
        $key = str_replace('.', ':', $key);
      }
    }

    return $key;
  }

  /**
   * Implements the magic sleep method.
   */
  public function __sleep() {
    // Prevent serialization of any attached service and Token data.
    // When a component actually tries to serialize this service (which
    // normally must not happen), this object will not work properly and
    // fail hard. When such situation occurs, the responsible component needs
    // to be fixed so that it does not try to serialize the Token service.
    return [];
  }

}
