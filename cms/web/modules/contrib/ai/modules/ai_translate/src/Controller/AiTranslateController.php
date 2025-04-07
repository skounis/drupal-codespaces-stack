<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines an AI Translate Controller.
 */
class AiTranslateController extends ControllerBase {

  use DependencySerializationTrait;

  /**
   * Text extractor service.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * Text translator service.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface
   */
  protected TextTranslatorInterface $aiTranslator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->aiTranslator = $container->get('ai_translate.text_translator');
    return $instance;
  }

  /**
   * Add requested entity translation to the original content.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_from
   *   Source language code.
   * @param string $lang_to
   *   Target language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translation
   *   overview page by showing a success or error message.
   */
  public function translate(string $entity_type, string $entity_id, string $lang_from, string $lang_to) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    // From UI, translation is always request from default entity language,
    // but nothing stops users from using different $lang_from.
    if ($entity->language()->getId() !== $lang_from
      && $entity->hasTranslation($lang_from)) {
      $entity = $entity->getTranslation($lang_from);
    }

    $redirectUrl = $entity->toUrl('drupal:content-translation-overview');
    $response = new RedirectResponse($redirectUrl->setAbsolute()->toString());

    // @todo support updating existing translations.
    if ($entity->hasTranslation($lang_to)) {
      $this->messenger()->addMessage('Translation already exists.');
      $response->send();
      return $response;
    }

    $textMetadata = $this->textExtractor->extractTextMetadata($entity);

    // Creates a batch builder to translate text metadata.
    $batchBuilder = (new BatchBuilder())
      ->setTitle($this->t('Translating entity content with AI'))
      ->setInitMessage($this->t('Batch is starting'))
      ->setErrorMessage($this->t('Batch has encountered an error'));

    foreach ($textMetadata as $singleMeta) {
      $batchBuilder->addOperation([$this, 'translateSingleText'], [
        $singleMeta,
        $langNames[$lang_from],
        $langNames[$lang_to],
      ]);
    }
    $batchBuilder->addOperation([$this, 'insertTranslation'], [$entity, $lang_to]);
    batch_set($batchBuilder->toArray());
    return batch_process($redirectUrl);
  }

  /**
   * Finished operation.
   */
  public static function finish($success, $results, $operations, $duration) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('All terms have been processed.'));
    }
  }

  /**
   * Batch callback - translate a single text.
   *
   * @param array $singleText
   *   Chunk of text metadata to translate.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public function translateSingleText(
    array $singleText,
    LanguageInterface $langFrom,
    LanguageInterface $langTo,
    array &$context,
  ) {
    // Translate the content.
    try {
      $translated_text = $this->aiTranslator->translateContent(
        $singleText['value'], $langTo, $langFrom);
    }
    catch (TranslationException) {
      $context['results']['failures'][] = $singleText['value'];
      return;
    }

    // Checks if the field allows HTML and decodes the HTML entities.
    if (isset($singleText['format'])) {
      $format = $singleText['format'];
      if (FilterFormat::load($format)) {
        $translated_text = html_entity_decode($translated_text);
      }
    }

    $singleText['translated'] = $translated_text;
    $context['results']['processedTranslations'][] = $singleText;
  }

  /**
   * Batch callback - insert processed texts back into the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to translate.
   * @param string $lang_to
   *   Language code of translation.
   * @param array $context
   *   Text metadata containing both source values and translation.
   */
  public function insertTranslation(
    ContentEntityInterface $entity,
    string $lang_to,
    array &$context,
  ) {
    $translation = $entity->addTranslation($lang_to);
    // Keep published status when translating.
    if ($entity instanceof EntityPublishedInterface) {
      $entity->isPublished() ? $translation->setPublished()
        : $translation->setUnpublished();
    }
    $this->textExtractor->insertTextMetadata($translation,
      $context['results']['processedTranslations']);
    try {
      $translation->save();
      $this->messenger()->addStatus($this->t('Content translated successfully.'));
    }
    catch (\Throwable $exception) {
      $this->getLogger('ai_translate')->warning($exception->getMessage());
      $this->messenger()->addError($this->t('There was some issue with content translation.'));
    }
  }

}
