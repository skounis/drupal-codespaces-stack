<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Action to translate a value.
 *
 * @Action(
 *   id = "eca_translate",
 *   label = @Translation("Translate"),
 *   description = @Translation("Translate a given value, and store the translated value as a token."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Translate extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $token = $this->tokenService;
    $name = $this->configuration['token_name'];
    $value = $this->configuration['value'];
    $target_langcode = $this->configuration['target_langcode'];
    if ($target_langcode === '_interface') {
      $target_langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    elseif ($target_langcode === '_preferred') {
      $target_langcode = $this->currentUser->getPreferredLangcode();
    }
    elseif ($target_langcode === '_eca_token') {
      $target_langcode = $this->getTokenValue('target_langcode', $this->languageManager->getCurrentLanguage()->getId());
    }

    $translatable = TRUE;

    if ($this->configuration['use_yaml']) {
      try {
        $value = $this->yamlParser->parse($value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a value in action "eca_translate" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      // Allow direct assignment of available data from the Token environment.
      $value = $token->getOrReplace($value);

      // Convert to proper values and check translatability.
      if ($value instanceof DataTransferObject) {
        $value = $value->toArray();
        if (isset($value[0]) && count($value) === 1 && (is_scalar($value[0]) || ($value[0] instanceof TranslatableMarkup))) {
          $value = reset($value);
        }
      }
      elseif ($value instanceof TranslatableMarkup) {
        $value = $value->getUntranslatedString();
      }
      elseif ($value instanceof TranslatableInterface) {
        // Setting to FALSE here because we already fetch the translation,
        // if available.
        $translatable = FALSE;
        if ($value->language()->getId() !== $target_langcode) {
          $value = $value->hasTranslation($target_langcode) ? $value->getTranslation($target_langcode) : $value->addTranslation($target_langcode);
        }
      }
      elseif (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
        $value = (string) $value;
      }
      else {
        $translatable = FALSE;
      }
    }

    if (is_array($value)) {
      array_walk_recursive($value, static function (&$v) use (&$translatable) {
        if (!is_scalar($v) && (!is_object($v) || !method_exists($v, '__toString'))) {
          $translatable = FALSE;
        }
      });
    }
    elseif ($value === NULL || $value === '') {
      $translatable = FALSE;
    }

    if ($translatable) {
      if (is_array($value)) {
        array_walk_recursive($value, function (&$v) use (&$target_langcode) {
          // @codingStandardsIgnoreLine
          $v = $this->t($v, [], ['langcode' => $target_langcode]);
        });
      }
      else {
        // @codingStandardsIgnoreLine
        $value = $this->t($value, [], ['langcode' => $target_langcode]);
      }
    }

    $token->addTokenData($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'value' => '',
      'use_yaml' => FALSE,
      'target_langcode' => '_interface',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The translation result will be stored in this token.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -50,
      '#eca_token_reference' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value to translate'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -40,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above value as YAML format'),
      '#description' => $this->t('Nested data can be set using YAML format, for example <em>mykey: "My value"</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -30,
    ];
    $langcodes = [
      '_interface' => $this->t('Interface language'),
      '_preferred' => $this->t('Preferred language of current user'),
    ];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $langcodes[$langcode] = $language->getName();
    }
    $form['target_langcode'] = [
      '#type' => 'select',
      '#options' => $langcodes,
      '#default_value' => $this->configuration['target_langcode'],
      '#title' => $this->t('Target language'),
      '#description' => $this->t('Define the language that the given value should be translated to.'),
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    $this->configuration['target_langcode'] = $form_state->getValue('target_langcode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

  /**
   * Set the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager): void {
    $this->languageManager = $language_manager;
  }

}
