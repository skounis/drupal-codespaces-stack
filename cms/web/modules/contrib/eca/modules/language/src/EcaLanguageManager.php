<?php

namespace Drupal\eca_language;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageManager;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;

/**
 * Extends the language manager to set the current language on runtime.
 */
class EcaLanguageManager extends ConfigurableLanguageManager {

  /**
   * The current language to use.
   *
   * @var \Drupal\Core\Language\LanguageInterface|null
   */
  protected ?LanguageInterface $currentLanguage = NULL;

  /**
   * The decorated language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $decoratedManager;

  /**
   * Set language manager that is being decorated by this service.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $manager
   *   The language manager that is being decorated.
   */
  public function setDecoratedLanguageManager(ConfigurableLanguageManagerInterface $manager): void {
    $this->decoratedManager = $manager;
  }

  /**
   * Set the language code of the currently used language.
   *
   * @param string|null $langcode
   *   The language code. Set to NULL to unset the currently used language.
   *
   * @throws \InvalidArgumentException
   *   When the requested language code is not available.
   */
  public function setCurrentLangcode(?string $langcode): void {
    if (is_null($langcode)) {
      $language = NULL;
    }
    elseif (!($language = $this->getLanguage($langcode))) {
      throw new \InvalidArgumentException(sprintf("The requested langcode %s is not available.", $langcode));
    }
    $this->currentLanguage = $language;
  }

  /**
   * {@inheritdoc}
   */
  public function getNegotiator() {
    return $this->decoratedManager->getNegotiator();
  }

  /**
   * {@inheritdoc}
   */
  public function setNegotiator(LanguageNegotiatorInterface $negotiator): void {
    parent::setNegotiator($negotiator);
    $this->decoratedManager->setNegotiator($negotiator);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinedLanguageTypes() {
    return $this->decoratedManager->getDefinedLanguageTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function saveLanguageTypesConfiguration(array $values): void {
    $this->decoratedManager->saveLanguageTypesConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLockedLanguageWeights(): void {
    $this->decoratedManager->updateLockedLanguageWeights();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageConfigOverride($langcode, $name) {
    return $this->decoratedManager->getLanguageConfigOverride($langcode, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageConfigOverrideStorage($langcode) {
    return $this->decoratedManager->getLanguageConfigOverrideStorage($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getStandardLanguageListWithoutConfigured() {
    return $this->decoratedManager->getStandardLanguageListWithoutConfigured();
  }

  /**
   * {@inheritdoc}
   */
  public function getNegotiatedLanguageMethod($type = LanguageInterface::TYPE_INTERFACE) {
    return $this->decoratedManager->getNegotiatedLanguageMethod($type);
  }

  /**
   * {@inheritdoc}
   */
  public function isMultilingual() {
    return $this->decoratedManager->isMultilingual();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageTypes() {
    return $this->decoratedManager->getLanguageTypes();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinedLanguageTypesInfo() {
    return $this->decoratedManager->getDefinedLanguageTypesInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentLanguage($type = LanguageInterface::TYPE_INTERFACE) {
    if (isset($this->currentLanguage) && ($type === LanguageInterface::TYPE_INTERFACE || $type === LanguageInterface::TYPE_URL)) {
      return $this->currentLanguage;
    }
    return $this->decoratedManager->getCurrentLanguage($type);
  }

  /**
   * {@inheritdoc}
   */
  public function reset($type = NULL) {
    $this->currentLanguage = NULL;
    parent::reset($type);
    $this->decoratedManager->reset($type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLanguage() {
    return $this->decoratedManager->getDefaultLanguage();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE) {
    return $this->decoratedManager->getLanguages($flags);
  }

  /**
   * {@inheritdoc}
   */
  public function getNativeLanguages() {
    return $this->decoratedManager->getNativeLanguages();
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage($langcode) {
    return $this->decoratedManager->getLanguage($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageName($langcode) {
    return $this->decoratedManager->getLanguageName($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLockedLanguages($weight = 0) {
    return $this->decoratedManager->getDefaultLockedLanguages($weight);
  }

  /**
   * {@inheritdoc}
   */
  public function isLanguageLocked($langcode) {
    return $this->decoratedManager->isLanguageLocked($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackCandidates(array $context = []) {
    return $this->decoratedManager->getFallbackCandidates($context);
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks($type, Url $url) {
    return $this->decoratedManager->getLanguageSwitchLinks($type, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigOverrideLanguage(?LanguageInterface $language = NULL) {
    parent::setConfigOverrideLanguage($language);
    $this->decoratedManager->setConfigOverrideLanguage($language);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigOverrideLanguage() {
    return $this->decoratedManager->getConfigOverrideLanguage();
  }

}
