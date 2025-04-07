<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Password\DefaultPasswordGenerator;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Random\RandomException;

/**
 * Action to set a random token value.
 *
 * @Action(
 *   id = "eca_token_set_random_value",
 *   label = @Translation("Token: set random value"),
 *   description = @Translation("Create a random value and store it in a token."),
 *   eca_version_introduced = "2.1.0"
 * )
 */
class TokenSetRandomValue extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $name = trim($this->configuration['token_name']);
    if ($name === '') {
      // Without a token name specified, a token cannot be set.
      return;
    }

    $mode = $this->configuration['mode'];
    if ($mode === '_eca_token') {
      $mode = $this->getTokenValue('mode', 'string');
    }
    [$mode, $subMode] = explode(' ', $mode . ' placeholder');

    $length = $this->tokenService->replace($this->configuration['length']);

    switch ($mode) {
      case 'string':
        $random = new Random();
        $value = $random->string((int) $length, $subMode === 'unique');
        break;

      case 'name':
        $random = new Random();
        $value = $random->name((int) $length, $subMode === 'unique');
        break;

      case 'machine_name':
        $random = new Random();
        $value = $random->machineName((int) $length, $subMode === 'unique');
        break;

      case 'word':
        $random = new Random();
        $value = $random->word((int) $length);
        break;

      case 'sentences':
        $random = new Random();
        $value = $random->sentences((int) $length, $subMode === 'capitalize');
        break;

      case 'paragraphs':
        $random = new Random();
        $value = $random->paragraphs((int) $length);
        break;

      case 'bytes':
        try {
          $value = random_bytes((int) $length);
        }
        catch (RandomException) {
          $value = '';
        }
        break;

      case 'password':
        $pwGen = new DefaultPasswordGenerator();
        $value = $pwGen->generate((int) $length);
        break;

      case 'integer':
        $parts = explode(',', $length);
        $min = PHP_INT_MIN;
        $max = PHP_INT_MAX;
        if (isset($parts[0]) && intval($parts[0])) {
          $min = (int) $parts[0];
        }
        if (isset($parts[1]) && intval($parts[1])) {
          $max = (int) $parts[1];
        }
        try {
          $value = random_int($min, $max);
        }
        catch (RandomException) {
          $value = '';
        }
        break;

      case 'image':
        $parts = explode(',', $length);
        $min = '100x100';
        $max = '1920x1080';
        if (!empty($parts[0])) {
          $min = $parts[0];
        }
        if (!empty($parts[1])) {
          $max = $parts[1];
        }
        $random = new Random();
        $value = $random->image('temporary://', $min, $max);
        break;

      default:
        $value = '';

    }

    if ($value !== '') {
      $this->tokenService->addTokenData($name, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'mode' => 'string',
      'length' => '8',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -30,
      '#description' => $this->t('Provide the name of a token where the value should be stored.'),
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('The type of random value to generate.'),
      '#options' => [
        'bytes' => $this->t('Bytes'),
        'image' => $this->t('Image'),
        'integer' => $this->t('Integer'),
        'machine_name unique' => $this->t('Machine name (unique)'),
        'machine_name' => $this->t('Machine name'),
        'name unique' => $this->t('Name (unique)'),
        'name' => $this->t('Name'),
        'paragraphs' => $this->t('Paragraphs'),
        'password' => $this->t('Password'),
        'sentences capitalize' => $this->t('Sentences (capitalize all words)'),
        'sentences' => $this->t('Sentences'),
        'string unique' => $this->t('String (unique)'),
        'string' => $this->t('String'),
        'word' => $this->t('Word'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['length'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Length or min/max'),
      '#description' => $this->t('The length or min/max range of the random value. For sentences, this value determines the number of words and for paragraphs it determines the number of paragraphs. For integer mode, please provide comma separated values for minimum and maximum value. Missing values default to PHPs built in mix and max integer values. For the image mode, please provide a comma separated min and max resolution, defaults to "100x100,1920x1080". For the image mode, the token will contain the path to the generated random image file.'),
      '#default_value' => $this->configuration['length'],
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['mode'] = $form_state->getValue('mode');
    $this->configuration['length'] = $form_state->getValue('length');
    parent::submitConfigurationForm($form, $form_state);
  }

}
