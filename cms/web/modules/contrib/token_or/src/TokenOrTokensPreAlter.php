<?php

namespace Drupal\token_or;

use Drupal\token\TokenInterface;

/**
 * Tokens Pre Alter Service.
 */
class TokenOrTokensPreAlter {

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\TokenInterface
   */
  protected $token;

  /**
   * Constructor.
   *
   * @param \Drupal\token\TokenInterface $token
   *   The token.
   */
  public function __construct(TokenInterface $token) {
    $this->token = $token;
  }

  /**
   * Performs a tokens pre alter.
   */
  public function tokensPreAlter(&$text, $data, $options) {
    $matches = [];

    if (!$text) {
      return;
    }

    preg_match_all("/\[[^\[\]]+\]/", $text, $matches);

    if (empty($matches)) {
      return;
    }

    foreach (reset($matches) as $match) {
      if (strpos($match, '|') !== FALSE) {
        $match_clean = substr(substr($match, 1), 0, -1);
        $sub_tokens = explode('|', $match_clean);

        foreach ($sub_tokens as $sub_token) {
          if (substr($sub_token, 0, 1) === '"' && substr($sub_token, -1, 1) === '"') {
            // This is a string replacement.
            $result = substr(substr($sub_token, 1), 0, -1);
          }
          else {
            // This is a token replacement.
            $result = $this->token->replace('[' . $sub_token . ']', $data, $options);
          }
          if ($result) {
            $text = str_replace($match, $result, $text);
            break;
          }
        }
        $text = str_replace($match, '', $text);
      }
    }
  }

}
