<?php

namespace Drupal\automatic_updates\Commands;

use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Performs an automatic core update, if any updates are available.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. This command should not be called directly,
 *   and this class should not be used by external code.
 */
final class RunCommand extends AutomaticUpdatesCommandBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    parent::configure();

    $this->setName('run')
      ->setDescription('Automatically updates Drupal core, if automatic updates are enabled (via the update settings form) and any updates are available.')
      // For simplicity, we want people to invoke the `auto-update` command with
      // no arguments, so don't show this command in the command list.
      ->setHidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);

    $runner = $this->container->get(CronUpdateRunner::class);
    if ($runner->getMode() === $runner::DISABLED) {
      $message = $this->t('Automatic updates are disabled. Visit the update settings form at @url to enable them.', [
        '@url' => Url::fromRoute('update.settings')->toString(),
      ]);
      $this->io->error((string) $message);
      return static::SUCCESS;
    }

    $release = $this->stage->getTargetRelease();
    if ($release) {
      $message = $this->t('Updating Drupal core to @version. This may take a while.', [
        '@version' => $release->getVersion(),
      ]);
      $this->io->info((string) $message);
      $this->stage->performUpdate();
    }
    else {
      $this->io->info((string) $this->t('There is no Drupal core update available.'));
      $this->runStatusChecks();
    }

    $this->processCleanupQueue();
    return static::SUCCESS;
  }

  /**
   * Processes the queue to delete defunct stage directories.
   */
  private function processCleanupQueue(): void {
    $verbose = $this->io->isVerbose();
    if ($verbose) {
      $this->io->writeln((string) $this->t('Deleting unused stage directories...'));
    }

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->container->get(QueueFactory::class)
      ->get('package_manager_cleanup');
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance('package_manager_cleanup');

    $items_processed = 0;
    while ($items_processed < 3 && ($item = $queue->claimItem())) {
      $items_processed++;

      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        if ($verbose) {
          $message = (string) $this->t('Unused stage directory deleted: @dir', ['@dir' => $item->data]);
          $this->io->writeln($message);
        }
      }
      catch (\Throwable $e) {
        $queue->releaseItem($item);
        $message = (string) $this->t('Could not delete unused stage directory @dir due to exception: @message', [
          '@dir' => $item->data,
          '@message' => $e->getMessage(),
        ]);
        $this->io->warning($message);
      }
    }
  }

}
