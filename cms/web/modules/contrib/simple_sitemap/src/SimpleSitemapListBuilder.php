<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Form\StatusForm;

/**
 * Defines a class to build a listing of sitemap entities.
 */
class SimpleSitemapListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_sitemap_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Sitemap');
    $header['type'] = $this->t('Type');
    $header['status'] = $this->t('Status');
    $header['count'] = $this->t('Link count');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity
   *   The entity for this row of the list.
   */
  public function buildRow(EntityInterface $entity) {
    $row['name']['#markup'] = '<span title="' . $entity->get('description') . '">' . $entity->label() . '</span>';
    $row['type']['#markup'] = '<span title="' . $entity->getType()->get('description') . '">' . $entity->getType()->label() . '</span>';
    $row['status']['#markup'] = $this->t('disabled');
    $row['count']['#markup'] = '';

    if ($entity->isEnabled()) {
      $row['status']['#markup'] = $this->t('pending');

      /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity */
      if ($entity->fromPublishedAndUnpublished()->getChunkCount()) {
        switch ($entity->contentStatus()) {

          case SimpleSitemap::SITEMAP_UNPUBLISHED:
            $row['status']['#markup'] = $this->t('generating');
            break;

          case SimpleSitemap::SITEMAP_PUBLISHED:
          case SimpleSitemap::SITEMAP_PUBLISHED_GENERATING:
            $row['name']['#markup'] = '<a title ="' . $entity->get('description')
              . '" href="' . $entity->toUrl()->toString() . '" target="_blank">'
              . $entity->label() . '</a>';

            $created = \Drupal::service('date.formatter')->format($entity->fromPublished()->getCreated());
            $row['status']['#markup'] = $entity->contentStatus() === SimpleSitemap::SITEMAP_PUBLISHED
              ? $this->t('published on @time', ['@time' => $created])
              : $this->t('published on @time, regenerating', ['@time' => $created]);

            $row['count']['#markup'] = $entity->fromPublished()->getLinkCount();

            break;
        }
      }
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form = \Drupal::formBuilder()->getForm(StatusForm::class) + $form;
    $form['entities']['#empty'] = $this->t('No sitemaps have been defined yet. <a href="@url">Add a new one</a>.', [
      '@url' => Url::fromRoute('simple_sitemap.add')->toString(),
    ]);

    return $form;
  }

}
