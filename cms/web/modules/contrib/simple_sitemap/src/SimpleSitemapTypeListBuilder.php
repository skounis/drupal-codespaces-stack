<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of sitemap type entities.
 */
class SimpleSitemapTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Sitemap type');
    $header['description'] = $this->t('Description');
    $header['sitemap_generator'] = $this->t('Sitemap generator');
    $header['url_generators'] = $this->t('URL generators');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface $entity
   *   The entity for this row of the list.
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['description'] = (string) $entity->get('description');
    $row['sitemap_generator'] = $entity->getSitemapGenerator()->label();
    $row['url_generators']['data']['#markup'] = '';
    foreach ($entity->getUrlGenerators() as $generator) {
      $row['url_generators']['data']['#markup'] .= '<div>' . $generator->label() . '</div>';
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    return [
      ['title' => $this->t('Edit'), 'url' => $entity->toUrl('edit-form')],
      ['title' => $this->t('Delete'), 'url' => $entity->toUrl('delete-form')],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No sitemap types have been defined yet. <a href="@url">Add a new one</a>.', [
      '@url' => Url::fromRoute('simple_sitemap_type.add')->toString(),
    ]);

    return $build;
  }

}
