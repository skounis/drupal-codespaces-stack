<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\FieldTextExtractorInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field text extractor plugin for layout builder content.
 *
 * The only supported section type is inline block.
 * Make sure that all content block fields displayed in layout builder
 * are configured to be translatable.
 */
#[FieldTextExtractor(
  id: "layout_builder",
  label: new TranslatableMarkup('layout_builder'),
  field_types: [
    'layout_section',
  ]
)]
class LbFieldExtractor implements FieldTextExtractorInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Session storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected SectionStorageManagerInterface $sectionStorageManager;

  /**
   * Text extractor.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static();
    $instance->config = $container->get('config.factory')->get('ai_translate.settings');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    static $blockStorage;
    if ($entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $textMeta = [];
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $sectionList */
    $sectionList = $entity->get($fieldName);
    foreach ($sectionList->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof InlineBlock) {
          continue;
        }
        // Mimic behavior of (protected) InlineBlock::getEntity().
        $blockEntity = NULL;
        $blockConfig = $plugin->getConfiguration();
        if (!isset($blockStorage)) {
          $blockStorage = $this->entityTypeManager->getStorage('block_content');
        }
        if (!empty($blockConfig['block_serialized'])) {
          $blockEntity = unserialize($blockConfig['block_serialized']);
        }
        elseif (!empty($blockConfig['block_revision_id'])) {
          $blockEntity = $blockStorage->loadRevision($blockConfig['block_revision_id']);
        }
        else {
          $blockEntity = $blockStorage->create([
            'type' => $plugin->getDerivativeId(),
            'reusable' => FALSE,
          ]);
        }
        if (!$blockEntity instanceof ContentEntityInterface) {
          continue;
        }
        foreach ($this->textExtractor->extractTextMetadata($blockEntity) as $subMeta) {
          $textMeta[] = ['delta' => $blockEntity->uuid()] + $subMeta;
        }
      }
    }
    return $textMeta;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(
    ContentEntityInterface $entity,
    string $fieldName,
    array $textMeta,
  ) : void {
    // Don't do anything if the field is empty.
    if ($entity->get($fieldName)->isEmpty()) {
      return;
    }

    static $blockStorage;
    if (!isset($blockStorage)) {
      $blockStorage = $this->entityTypeManager->getStorage('block_content');
    }
    $translationLanguage = $entity->language()->getId();
    foreach ($blockStorage->loadByProperties(['uuid' => array_keys($textMeta)]) as $blockEntity) {
      // @todo Decide if/when we should update existing translation.
      $blockEntity = $blockEntity->hasTranslation($translationLanguage)
        ? $blockEntity->getTranslation($translationLanguage)
        : $blockEntity->addTranslation($translationLanguage);

      foreach ($textMeta[$blockEntity->uuid()] as $subFieldName => $subValue) {
        foreach ($subValue as &$singleSubValue) {
          unset($singleSubValue['field_name']);
          unset($singleSubValue['field_type']);
        }
        $blockEntity->set($subFieldName, $subValue);
      }
      $blockStorage->save($blockEntity);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    // Always translate layout builder. Is that correct?
    return TRUE;
  }

}
