<?php

namespace Drupal\automatic_updates\Commands;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\package_manager\ProjectInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Finishes an automatic core update by running post-apply tasks.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. This command should not be called directly,
 *   and this class should not be used by external code.
 */
final class PostApplyCommand extends AutomaticUpdatesCommandBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    parent::configure();

    $this
      ->setName('post-apply')
      // This command is for internal use only and should never be called
      // directly. We don't want it to show up in the application command list.
      ->setHidden()
      ->addArgument('stage-id', InputArgument::REQUIRED);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);

    $this->io->info((string) $this->t('Running post-apply tasks and final clean-up...'));
    $this->stage->handlePostApply($input->getArgument('stage-id'));

    $message = $this->t('Drupal core was successfully updated to @version!', [
      '@version' => (new ProjectInfo('drupal'))->getInstalledVersion(),
    ]);
    $this->io->success((string) $message);

    $this->runStatusChecks();
    return static::SUCCESS;
  }

}
