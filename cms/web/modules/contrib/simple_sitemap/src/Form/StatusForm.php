<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\simple_sitemap\Manager\Generator as SimplesitemapOld;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\simple_sitemap\Settings;

/**
 * Provides form to manage sitemap status.
 */
class StatusForm extends SimpleSitemapFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The simple_sitemap.queue_worker service.
   *
   * @var \Drupal\simple_sitemap\Queue\QueueWorker
   */
  protected $queueWorker;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * StatusForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker
   *   The simple_sitemap.queue_worker service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    SimplesitemapOld $generator,
    Settings $settings,
    FormHelper $form_helper,
    Connection $database,
    DateFormatterInterface $date_formatter,
    QueueWorker $queue_worker,
    RendererInterface $renderer,
  ) {
    parent::__construct(
      $config_factory,
      $typedConfigManager,
      $generator,
      $settings,
      $form_helper
    );
    $this->db = $database;
    $this->dateFormatter = $date_formatter;
    $this->queueWorker = $queue_worker;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['#attached']['library'][] = 'simple_sitemap/sitemaps';

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sitemap status'),
      '#markup' => '<div class="description">' . $this->t('Sitemaps can be regenerated on demand here.') . '</div>',
    ];

    $form['status']['actions'] = [
      '#prefix' => '<div class="clearfix"><div class="form-item">',
      '#suffix' => '</div></div>',
    ];

    $form['status']['actions']['rebuild_queue_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild queue'),
      '#submit' => [self::class . '::rebuildQueue'],
      '#validate' => [],
    ];

    $form['status']['actions']['regenerate_submit'] = [
      '#type' => 'submit',
      '#value' => $this->queueWorker->generationInProgress()
        ? $this->t('Resume generation')
        : $this->t('Rebuild queue & generate'),
      '#submit' => [self::class . '::generate'],
      '#validate' => [],
    ];

    $form['status']['progress'] = [
      '#prefix' => '<div class="clearfix">',
      '#suffix' => '</div>',
    ];

    $form['status']['progress']['title']['#markup'] = $this->t('Progress of sitemap regeneration');

    $total_count = $this->queueWorker->getInitialElementCount();
    if ($total_count > 0) {
      $indexed_count = $this->queueWorker->getProcessedElementCount();
      $percent = round(100 * $indexed_count / $total_count);

      // With all results processed, there still may be some stashed results to
      // be indexed.
      $percent = $percent == 100 && $this->queueWorker->generationInProgress() ? 99 : $percent;

      $index_progress = [
        '#theme' => 'progress_bar',
        '#percent' => $percent,
        '#message' => $this->t('@indexed out of @total queue items have been processed.<br>Each sitemap is published after all of its items have been processed.', [
          '@indexed' => $indexed_count,
          '@total' => $total_count,
        ]),
      ];
      $form['status']['progress']['bar']['#markup'] = $this->renderer->render($index_progress);
    }
    else {
      $form['status']['progress']['bar']['#markup'] = '<div class="description">' . $this->t('There are no items to be indexed.') . '</div>';
    }

    return $form;
  }

  /**
   * Generates the sitemap content.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function generate(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
    $generator = \Drupal::service('simple_sitemap.generator');
    $generator->generate();
  }

  /**
   * Rebuilds the queue.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function rebuildQueue(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
    $generator = \Drupal::service('simple_sitemap.generator');
    $generator->rebuildQueue();
  }

}
