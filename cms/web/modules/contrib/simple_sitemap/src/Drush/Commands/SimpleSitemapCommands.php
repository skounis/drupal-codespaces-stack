<?php

namespace Drupal\simple_sitemap\Drush\Commands;

use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Exception\InvalidOptionException;

/**
 * Provides Drush commands for managing sitemaps.
 */
final class SimpleSitemapCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * SimplesitemapCommands constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The simple_sitemap.generator service.
   */
  public function __construct(protected Generator $generator) {
    parent::__construct();
  }

  /**
   * Regenerate all sitemaps or continue generation.
   */
  #[CLI\Command(name: 'simple-sitemap:generate', aliases: ['ssg', 'simple-sitemap-generate'])]
  #[CLI\Usage(name: 'drush simple-sitemap:generate', description: 'Regenerate all sitemaps or continue generation.')]
  public function generate(): void {
    $this->generator->generate(QueueWorker::GENERATE_TYPE_DRUSH);
  }

  /**
   * Queue all or specific sitemaps for regeneration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'simple-sitemap:rebuild-queue', aliases: ['ssr', 'simple-sitemap-rebuild-queue'])]
  #[CLI\Option(name: 'variants', description: 'Queue all or specific sitemaps for regeneration.')]
  #[CLI\Usage(name: 'drush simple-sitemap:rebuild-queue', description: 'Rebuild the sitemap queue for all sitemaps.')]
  #[CLI\Usage(name: 'drush simple-sitemap:rebuild-queue --variants=default,test', description: "Rebuild the sitemap queue queueing only sitemaps <info>default</info> and <info>test</info>.")]
  public function rebuildQueue(array $options = ['variants' => '']): void {
    $variants = array_keys(SimpleSitemap::loadMultiple());
    if (isset($options['variants']) && (string) $options['variants'] !== '') {
      $chosen_variants = array_map('trim', array_filter(explode(',', (string) $options['variants'])));
      if (!empty($erroneous_variants = array_diff($chosen_variants, $variants))) {
        $message = 'The following sitemaps do not exist: ' . implode(', ', $erroneous_variants) . '.'
          . ($variants
            ? (' Available variants are: ' . implode(', ', $variants))
            : '')
          . '.';
        throw new InvalidOptionException($message);
      }
      $variants = $chosen_variants;
    }

    $this->generator->setSitemaps($variants)->rebuildQueue();

    $message = $variants
      ? 'The following sitemaps have been queued for regeneration: ' . implode(', ', $variants) . '.'
      : 'No sitemaps have been queued for regeneration.';
    $this->logger()->notice($message);
  }

}
