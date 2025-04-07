<?php

namespace Drupal\eca\Plugin\ECA;

use Drupal\Core\Render\Element;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Provides methods to modify plugin's configuration forms.
 */
trait PluginFormTrait {

  /**
   * {@inheritdoc}
   */
  abstract public function getPluginId();

  /**
   * Builds the token name for fields supporting "#eca_token_select_option".
   *
   * @param string $fieldKey
   *   The key of the field supporting that token.
   *
   * @return string
   *   The token name.
   */
  protected function buildTokenName(string $fieldKey): string {
    return implode('_', [$this->getPluginId(), $fieldKey]);
  }

  /**
   * Gets the token value for the field supporting "#eca_token_select_option".
   *
   * @param string $fieldKey
   *   The key of the field supporting that token.
   * @param string $default
   *   The default value if no token exists.
   *
   * @return string
   *   The token value if it exists and is a string, the default value
   *   otherwise.
   */
  protected function getTokenValue(string $fieldKey, string $default): string {
    $value = $this->tokenService->getTokenData($this->buildTokenName($fieldKey));
    if ($value instanceof DataTransferObject) {
      $value = $value->getValue();
    }
    return is_scalar($value) ? (string) $value : $default;
  }

  /**
   * Update the configuration form by adding ECA specific components.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   The updated form.
   */
  protected function updateConfigurationForm(array $form): array {
    $containsTokenReplacement = FALSE;
    foreach (Element::children($form) as $child_key) {
      $value = &$form[$child_key];
      if (!empty($value['#eca_token_replacement'])) {
        $containsTokenReplacement = TRUE;
        $description = 'This field supports tokens.';
        $separator = '<br/>';
      }
      elseif (!empty($value['#eca_token_reference'])) {
        $description = 'Please provide the token name only, without brackets.';
        $separator = ' ';
      }
      elseif (!empty($value['#eca_token_select_option']) && isset($value['#options']) && is_array($value['#options'])) {
        $value['#options']['_eca_token'] = 'Defined by token';
        if (($value['#required'] ?? FALSE) === FALSE) {
          $value['#options'][''] = 'undefined';
        }
        $description = 'When using the "Defined by token" option, make sure there is a token with this name: <em>' . $this->buildTokenName($child_key) . '</em>';
        $separator = '<br/>';
      }
      else {
        continue;
      }
      if (!isset($value['#description'])) {
        $value['#description'] = '';
      }
      $value['#description'] .= $separator . $description;
    }
    unset($value);
    if ($containsTokenReplacement) {
      $form['eca_token_info'] = [
        '#type' => 'markup',
        '#markup' => $this->t('This template supports the usage of tokens. Please refer to the token chapter in the ECA Guide, and if you install and enable the token module, you will find a link to the token browser right below the property panel, which shows you all available tokens.'),
      ];
    }
    return $form;
  }

}
