<?php

namespace Drupal\friendlycaptcha;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Service class for the local endpoint.
 */
class SiteVerify {

  public const SCALING_TTL_SECONDS = 30 * 60;
  public const SCALING = [
    0 => ['solutions' => 51, 'difficulty' => 122],
    4 => ['solutions' => 51, 'difficulty' => 130],
    10 => ['solutions' => 45, 'difficulty' => 141],
    20 => ['solutions' => 45, 'difficulty' => 149],
  ];
  public const EXPIRY_TIMES_5_MINUTES = 12;

  /**
   * The expirable key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected KeyValueStoreExpirableInterface $keyValueStore;

  /**
   * The config for this module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * SiteVerify service constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueFactory
   *   The expirable key value factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(KeyValueExpirableFactoryInterface $keyValueFactory, ConfigFactoryInterface $configFactory) {
    $this->keyValueStore = $keyValueFactory->get('friendlycaptcha');
    $this->config = $configFactory->get('friendlycaptcha.settings');
  }

  /**
   * Pad a string to a certain length with another string.
   *
   * @param string $hexValue
   *   The input string.
   * @param int $bytes
   *   Number of expected bytes after padding.
   * @param int $where
   *   Optional argument "where" can be
   *   STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH.
   *
   * @return string
   *   The padded string.
   *
   * @see https://github.com/FriendlyCaptcha/friendly-lite-server/blob/main/src/FriendlyCaptcha/Lite/Polite.php#L34
   */
  public static function padHex(string $hexValue, int $bytes, int $where = STR_PAD_LEFT): string {
    return str_pad($hexValue, $bytes * 2, '0', $where);
  }

  /**
   * Extracts a number of hex bytes from a string.
   *
   * @param string $string
   *   The string to extract the hex bytes from.
   * @param int $offset
   *   The offset from where to extract.
   * @param int $count
   *   The number of hex bytes to extract.
   *
   * @return false|string
   *   The extracted hex bytes, if extraction was possible, FALSE otherwise.
   *
   * @see https://github.com/FriendlyCaptcha/friendly-lite-server/blob/main/src/FriendlyCaptcha/Lite/Polite.php#L39
   */
  public static function extractHexBytes(string $string, int $offset, int $count) {
    return substr($string, $offset * 2, $count * 2);
  }

  /**
   * Gets the little endian as decimal value.
   *
   * @param string $hexValue
   *   The input string in hex.
   *
   * @return int
   *   The decimal value of the little endian.
   *
   * @see https://github.com/FriendlyCaptcha/friendly-lite-server/blob/main/src/FriendlyCaptcha/Lite/Polite.php#L44
   */
  public static function littleEndianHexToDec(string $hexValue): int {
    $bigEndianHex = implode('', array_reverse(str_split($hexValue, 2)));
    return hexdec($bigEndianHex);
  }

  /**
   * Anonymizes the client IP address.
   *
   * @param string $ip
   *   The client IP address.
   *
   * @return string
   *   The anonymoized IP address.
   *
   * @see https://github.com/FriendlyCaptcha/friendly-lite-server/blob/main/src/FriendlyCaptcha/Lite/Polite.php#L60
   */
  public static function anonymizeIp(string $ip): string {
    return preg_replace(
      ['/\.\d*$/', '/[\da-f]*:[\da-f]*$/'],
      ['.XXX', 'XXXX:XXXX'],
      $ip
    );
  }

  /**
   * Gets an SHA256 hash for a string buffer.
   *
   * @param string $buffer
   *   The string buffer.
   * @param string $key
   *   The signing key.
   *
   * @return string
   *   The hash.
   *
   * @see https://github.com/FriendlyCaptcha/friendly-lite-server/blob/main/src/FriendlyCaptcha/Lite/Polite.php#L69
   */
  public static function signBuffer(string $buffer, string $key): string {
    return hash_hmac('sha256', $buffer, $key);
  }

  /**
   * Verifies the given solution.
   *
   * @param string $solution
   *   The given solution.
   *
   * @return array
   *   The response as an array with keyed element called "success" with the
   *   boolean value indicating if the verification was successful. If not, the
   *   array contains a second item called "error" with an error message.
   *
   * @throws \SodiumException
   */
  public function verify(string $solution): array {
    if (empty($solution)) {
      return self::returnErrorEmptySolution();
    }
    $solutionArray = explode('.', $solution);
    if (count($solutionArray) < 3) {
      $errorMessage = new FormattableMarkup('Malformed solution: Expected at least 3 parts, got @count.', ['@count' => count($solutionArray)]);
      return self::returnMalformedSolution($errorMessage->__toString());
    }
    // $solutionArray consists of 4 entries. But only 3 parameters are needed
    // for verification. The 4th parameter is not used in the original code
    // so we do not assign it to any variable here. Original discussion and code
    // here: https://www.drupal.org/project/friendlycaptcha/issues/344179,
    // https://github.com/FriendlyCaptcha/friendly-lite-server/blob/e93be67590aa2f5216459047e5c14d14ac5ee90b/src/FriendlyCaptcha/Lite/Captcha.php#L75C47-L75C59
    [$signature, $puzzle, $solutions] = $solutionArray;
    $puzzleBin = base64_decode($puzzle);
    $puzzleHex = bin2hex($puzzleBin);
    if (($calculated = self::signBuffer($puzzleBin, $this->config->get('site_key'))) !== $signature) {
      $errorMessage = new FormattableMarkup("Signature mismatch: Calculated '@calculated', given '@given'.", [
        '@calculated' => $calculated,
        '@given' => $signature,
      ]);
      return self::returnSolutionInvalid($errorMessage->__toString());
    }
    // Only need to store as long as valid, after that the timeout will kick in.
    if (!$this->keyValueStore->setWithExpireIfNotExists($puzzleHex, TRUE, self::EXPIRY_TIMES_5_MINUTES * 300)) {
      $errorMessage = new FormattableMarkup("Replay: Puzzle '@puzzle' was already successfully used before.", ['@puzzle' => $puzzleHex]);
      return self::returnSolutionTimeoutOrDuplicate($errorMessage->__toString());
    }

    $numberOfSolutions = hexdec(self::extractHexBytes($puzzleHex, 14, 1));
    $timeStamp = hexdec(self::extractHexBytes($puzzleHex, 0, 4));
    $expiry = hexdec(self::extractHexBytes($puzzleHex, 13, 1));
    $expiryInSeconds = $expiry * 300;
    $solutionsHex = bin2hex(base64_decode($solutions));
    $age = time() - $timeStamp;
    if (($expiry !== 0) && $age > $expiryInSeconds) {
      $errorMessage = new FormattableMarkup("Timeout: Puzzle is too old ('@age' seconds, allowed: '@expiry').", [
        '@age' => $age,
        '@expiry' => $expiry,
      ]);
      return self::returnSolutionTimeoutOrDuplicate($errorMessage->__toString());
    }

    $d = hexdec(self::extractHexBytes($puzzleHex, 15, 1));
    $t = floor(2 ** ((255.999 - $d) / 8.0));
    $solutionSeenInThisRequest = [];
    for ($solutionIndex = 0; $solutionIndex < $numberOfSolutions; $solutionIndex++) {
      $currentSolution = self::extractHexBytes($solutionsHex, $solutionIndex * 8, 8);

      if (isset($solutionSeenInThisRequest[$currentSolution])) {
        return self::returnSolutionInvalid('Replay: Solution seen in this request before.');
      }
      $solutionSeenInThisRequest[$currentSolution] = TRUE;
      $fullSolution = self::padHex($puzzleHex, 120, STR_PAD_RIGHT) . $currentSolution;
      $blake2b256hash = bin2hex(sodium_crypto_generichash(hex2bin($fullSolution)));
      $first4Bytes = self::extractHexBytes($blake2b256hash, 0, 4);
      $first4Int = self::littleEndianHexToDec($first4Bytes);
      if ($first4Int >= $t) {
        $errorMessage = new FormattableMarkup("Invalid: Solution '@currentSolution' (index: '@solutionIndex') is invalid (@first4Int >= @t).", [
          '@currentSolution' => $currentSolution,
          '@solutionIndex' => $solutionIndex,
          '@first4Int' => $first4Int,
          '@t' => $t,
        ]);
        return self::returnSolutionInvalid($errorMessage->__toString());
      }
    }
    return self::returnResponse(TRUE);
  }

  /**
   * Helper function to return a response array for an invalid solution.
   *
   * @param string|null $errorMessage
   *   The error message.
   *
   * @return array
   *   The response array for an invalid solution.
   */
  private static function returnSolutionInvalid(?string $errorMessage = NULL): array {
    return self::returnResponse(FALSE, 'solution_invalid', $errorMessage);
  }

  /**
   * Helper function to return a response array for a timed-out solution.
   *
   * @param string|null $errorMessage
   *   The error message.
   *
   * @return array
   *   The response array for a timed.out solution.
   */
  private static function returnSolutionTimeoutOrDuplicate(?string $errorMessage = NULL): array {
    return self::returnResponse(FALSE, 'solution_timeout_or_duplicate', $errorMessage);
  }

  /**
   * Helper function to return a response array for an empty solution.
   *
   * @param string|null $errorMessage
   *   The error message.
   *
   * @return array
   *   The response array for an empty solution.
   */
  private static function returnErrorEmptySolution(?string $errorMessage = NULL): array {
    return self::returnResponse(FALSE, 'solution_missing', $errorMessage);
  }

  /**
   * Helper function to return a response array for a malformed solution.
   *
   * @param string|null $errorMessage
   *   The error message.
   *
   * @return array
   *   The response array for a malformed solution.
   */
  private static function returnMalformedSolution(?string $errorMessage = NULL): array {
    return self::returnResponse(FALSE, 'solution_malformed', $errorMessage);
  }

  /**
   * Helper function to prepare the response array.
   *
   * @param bool $success
   *   Whether the response is a success or not.
   * @param string|null $errorId
   *   An optional error id.
   * @param string|null $errorMessage
   *   An optional error message.
   *
   * @return array
   *   The response array.
   */
  private static function returnResponse(bool $success, ?string $errorId = NULL, $errorMessage = NULL): array {
    $result = [
      'success' => $success,
    ];
    if ($errorId !== NULL) {
      $result['error_id'] = $errorId;
    }
    if ($errorMessage !== NULL) {
      $result['error'] = $errorMessage;
    }
    return $result;
  }

}
