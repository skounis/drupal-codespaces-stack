<?php

namespace Drupal\token_or_webform;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\webform\WebformTokenManager;

/**
 * Tokens Pre Alter Service.
 *
 * @property \Drupal\Core\Utility\Token $token
 */
class TokenOrWebformTokenManager extends WebformTokenManager {

  /**
   * {@inheritdoc}
   */
  public function replace($text, ?EntityInterface $entity = NULL, array $data = [], array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!$text) {
      return $text;
    }

    if (is_array($text) || strpos($text, '|') === FALSE) {
      return parent::replace($text, $entity, $data, $options, $bubbleable_metadata);
    }
    $options['clear'] = 1;

    // For anonymous users remove all [current-user] tokens to prevent
    // anonymous user properties from being displayed.
    // For example, the [current-user:display-name] token will return
    // 'Anonymous', which is not an expected behavior.
    //
    // Updated to handle token_or's | syntax.
    if ($this->currentUser->isAnonymous() && strpos($text, '[current-user:') !== FALSE) {
      $text = preg_replace('/\[current-user:[^|]+\|/', '[', $text);
      // Removing current-user: might leave a single string token.
      // Let's clean that up.
      $text = preg_replace('/\["([^]"]+)"\]/', '$1', $text);
    }

    return parent::replace($text, $entity, $data, $options, $bubbleable_metadata);
  }

}
