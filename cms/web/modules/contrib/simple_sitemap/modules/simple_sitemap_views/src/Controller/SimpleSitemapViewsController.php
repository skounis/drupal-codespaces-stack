<?php

namespace Drupal\simple_sitemap_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\simple_sitemap_views\SimpleSitemapViews;

/**
 * Controller for Simple XML Sitemap Views admin page.
 */
class SimpleSitemapViewsController extends ControllerBase {

  /**
   * Views sitemap data.
   *
   * @var \Drupal\simple_sitemap_views\SimpleSitemapViews
   */
  protected $sitemapViews;

  /**
   * SimpleSitemapViewsController constructor.
   *
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   */
  public function __construct(SimpleSitemapViews $sitemap_views) {
    $this->sitemapViews = $sitemap_views;
  }

  /**
   * Builds a listing of indexed views displays.
   *
   * @return array
   *   A render array.
   */
  public function content(): array {
    $build['simple_sitemap_views'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('View'),
        $this->t('Display'),
        $this->t('Sitemaps'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No view displays are set to be indexed yet. <a href="@url">Edit a view.</a>', ['@url' => Url::fromRoute('entity.view.collection')->toString()]),
    ];

    $table = &$build['simple_sitemap_views'];

    if (empty($this->sitemapViews->getSitemaps())) {
      $table['#empty'] = $this->t('Please configure at least one <a href="@sitemaps_url">sitemap</a> to be of a <a href="@types_url">type</a> that implements the views URL generator.', [
        '@sitemaps_url' => Url::fromRoute('entity.simple_sitemap.collection')->toString(),
        '@types_url' => Url::fromRoute('entity.simple_sitemap_type.collection')->toString(),
      ]);
    }

    foreach ($this->sitemapViews->getIndexableViews() as $index => $view) {
      $table[$index]['view'] = ['#markup' => $view->storage->label()];
      $table[$index]['display'] = ['#markup' => $view->display_handler->display['display_title']];

      $sitemaps = $this->sitemapViews->getIndexableSitemaps($view);
      $variants = implode(', ', array_keys($sitemaps));
      $table[$index]['variants'] = ['#markup' => $variants];

      // Link to view display edit form.
      $display_edit_url = Url::fromRoute('entity.view.edit_display_form', [
        'view' => $view->id(),
        'display_id' => $view->current_display,
      ]);

      $table[$index]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'display_edit' => [
            'title' => $this->t('Edit'),
            'url' => $display_edit_url,
          ],
        ],
      ];
    }

    return $build;
  }

}
