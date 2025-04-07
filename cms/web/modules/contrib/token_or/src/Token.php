<?php

namespace Drupal\token_or;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\token\Token as OriginalToken;

/**
 * Override class for tokens.
 */
class Token extends OriginalToken {

  /**
   * {@inheritdoc}
   */
  public function replace($text, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $this->moduleHandler->alter('tokens_pre', $text, $data, $options);
    return parent::replace($text, $data, $options, $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function scan($text) {
    // Matches tokens with the following pattern: [$type:$name]
    // $type and $name may not contain [ ] characters.
    // $type may not contain : or whitespace characters, but $name may.
    preg_match_all('/
      \[             # [ - pattern start
      ([^\s\[\]:]+)  # match $type not containing whitespace : [ or ]
      :              # : - separator
      ([^\[\]|]+)    # match $name not containing [ or ] or | (changed from core!)
      \]             # ] - pattern end
      /x', $text, $matches);

    $types = $matches[1];
    $tokens = $matches[2];

    // Iterate through the matches, building an associative array containing
    // $tokens grouped by $types, pointing to the version of the token found in
    // the source text. For example, $results['node']['title'] = '[node:title]'.
    $results = [];
    for ($i = 0; $i < count($tokens); $i++) {
      $results[$types[$i]][$tokens[$i]] = $matches[0][$i];
    }

    // Find matches that have pipes.
    preg_match("/\[([^\[\]]+)\|([^\[\]]+)\]/", $text, $multi_matches);

    $sub_tokens = [];

    foreach ($multi_matches as $multi_match) {
      $sub_matches = explode('|', $multi_match);

      $filtered_sub_tokens = array_filter($sub_matches, function ($sub_match) {
        return strpos($sub_match, '"') === FALSE;
      });

      foreach ($filtered_sub_tokens as $filtered_sub_token) {
        $sub_tokens[] = '[' . $filtered_sub_token . ']';
      }
    }

    return NestedArray::mergeDeep($results, parent::scan(implode(' ', $sub_tokens)));
  }

}
