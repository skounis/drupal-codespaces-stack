<?php

namespace Drupal\simple_sitemap_engines;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search engine entity list builder.
 */
class SearchEngineListBuilder extends ConfigEntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * SearchEngineListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->state = $state;
    $this->config = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('state'),
      $container->get('config.factory')
    );
  }

  /**
   * Build the render array.
   */
  public function render(): array {
    return [
      'index_now_engines' => $this->renderIndexNowEngines(),
      'sitemap_submission_engines' => $this->renderSitemapSubmissionEngines(),
    ];
  }

  /**
   * Render sitemap submission engines.
   *
   * @return array
   *   The build array.
   */
  protected function renderSitemapSubmissionEngines(): array {
    $enabled = (bool) $this->config->get('simple_sitemap_engines.settings')->get('enabled');
    $build = [
      '#type' => 'details',
      '#open' => $enabled,
      '#title' => $this->t('Sitemap submission status (ping protocol)'),
      'table' => [
        '#type' => 'table',
        '#header' => [
          'label' => $this->t('Name'),
          'url' => $this->t('Submission URL'),
          'variants' => $this->t('Sitemaps'),
          'last_submitted' => $this->t('Last submitted'),
        ],
        '#rows' => [],
        '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      ],
      '#description' => $this->t('Submission settings can be configured <a href="@url">here</a>.<br/>The ping protocol is <strong>being deprecated</strong>, use IndexNow if applicable.',
        ['@url' => Url::fromRoute('simple_sitemap.engines.settings')->toString()]
      ),
    ];

    if ($enabled) {
      foreach (SimpleSitemapEngine::loadSitemapSubmissionEngines() as $entity) {
        $last_submitted = $this->state->get("simple_sitemap_engines.simple_sitemap_engine.{$entity->id()}.last_submitted", -1);
        $build['table']['#rows'][$entity->id()] = [
          'label' => $entity->label(),
          'url' => $entity->url,
          'variants' => implode(', ', $entity->sitemap_variants),
          'last_submitted' => $last_submitted !== -1
            ? $this->dateFormatter->format($last_submitted, 'short')
            : $this->t('Never'),
        ];
      }
    }

    $build['table']['#empty'] = $enabled
      ? $this->t('No search engines supporting sitemap submission have been found.')
      : $this->t('Sitemap submission is disabled.');

    return $build;
  }

  /**
   * Render IndexNow engines.
   *
   * @return array
   *   The build array.
   */
  protected function renderIndexNowEngines(): array {
    $enabled = (bool) $this->config->get('simple_sitemap_engines.settings')->get('index_now_enabled');
    $info = $this->state->get('simple_sitemap_engines.index_now.last');
    $build = [
      '#type' => 'details',
      '#open' => $enabled,
      '#title' => $this->t('Page submission status (IndexNow protocol)'),
      'table' => [
        '#type' => 'table',
        '#suffix' => $enabled && $info ? $this->t("The last IndexNow submission was <em>@entity</em> to @engine_label on @time", [
          '@entity' => $info['entity_label'] ?: $info['entity'],
          '@engine_label' => $info['engine_label'],
          '@time' => $this->dateFormatter->format($info['time'], 'short'),
        ]) : '',
        '#header' => [
          'label' => $this->t('Name'),
          'url' => $this->t('IndexNow URL'),
        ],
        '#rows' => [],
      ],
      '#description' => $this->t('IndexNow settings can be configured <a href="@url">here</a>.',
        ['@url' => Url::fromRoute('simple_sitemap.engines.settings')->toString()]
      ),
    ];

    if ($enabled) {
      foreach (SimpleSitemapEngine::loadIndexNowEngines() as $engine) {
        $build['table']['#rows'][$engine->id()] = [
          'label' => $engine->label(),
          'url' => $engine->index_now_url,
        ];
      }
    }

    $build['table']['#empty'] = $enabled
      ? $this->t('No search engines supporting IndexNow have been found.')
      : $this->t('IndexNow submission is disabled.');

    return $build;
  }

}
