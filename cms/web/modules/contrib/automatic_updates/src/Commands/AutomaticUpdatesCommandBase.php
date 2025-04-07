<?php

namespace Drupal\automatic_updates\Commands;

use Drupal\automatic_updates\ConsoleUpdateStage;
use Drupal\automatic_updates\CronUpdateRunner;
use Drupal\automatic_updates\StatusCheckMailer;
use Drupal\automatic_updates\Validation\StatusChecker;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DrupalKernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for Automatic Updates console commands that boot Drupal.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
abstract class AutomaticUpdatesCommandBase extends Command {

  /**
   * The I/O handler.
   *
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  protected SymfonyStyle $io;

  /**
   * The Drupal service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * The console update stage.
   *
   * @var \Drupal\automatic_updates\ConsoleUpdateStage
   */
  protected ConsoleUpdateStage $stage;

  /**
   * Constructs an AutomaticUpdatesCommandBase object.
   *
   * @param object $autoloader
   *   The autoloader, passed by reference so it can be decorated during
   *   Drupal's bootstrap process.
   */
  public function __construct(private object &$autoloader) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    parent::configure();

    $this->addOption('uri', mode: InputOption::VALUE_REQUIRED, description: 'The URI of the Drupal site, e.g. https://example.com or https://example.com/mysite.', default: 'https://default');
    $this->addOption('is-from-web', mode: InputOption::VALUE_NONE, description: 'This option is for internal use only and should not be passed.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
      throw new \DomainException('For security reasons, this command cannot be run as the superuser (root).');
    }

    $this->io = new SymfonyStyle($input, $output);

    // Detect the Drupal application root based on the location of the \Drupal
    // class.
    $drupal_root = dirname((new \ReflectionClass('\Drupal'))->getFileName(), 3);
    // We need to be in the Drupal root for everything to boot up properly.
    chdir($drupal_root);

    $uri = $input->getOption('uri');
    // If the --uri option did not include a scheme, prepend one.
    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      $uri = 'https://' . $uri;
    }
    $base_path = parse_url($uri, PHP_URL_PATH) ?? '/';

    // Ensure the SCRIPT_FILENAME and SCRIPT_NAME variables are accurate so that
    // Drupal can generate URLs correctly.
    $request = Request::create($uri, server: [
      'SCRIPT_FILENAME' => $drupal_root . '/index.php',
      'SCRIPT_NAME' => $base_path . 'index.php',
    ]);

    $kernel = DrupalKernel::createFromRequest($request, $this->autoloader, 'prod', app_root: $drupal_root)
      ->boot();
    $kernel->preHandle($request);
    $this->container = $kernel->getContainer();

    $this->stage = $this->container->get(ConsoleUpdateStage::class);
    $this->stage->output = $output;
    $this->stage->isFromWeb = $input->getOption('is-from-web');

    return static::SUCCESS;
  }

  /**
   * Runs status checks, and sends failure notifications if necessary.
   */
  protected function runStatusChecks(): void {
    assert($this->container instanceof ContainerInterface, 'Drupal is not booted.');

    /** @var \Drupal\Component\Datetime\TimeInterface $time */
    $time = $this->container->get(TimeInterface::class);
    /** @var \Drupal\automatic_updates\Validation\StatusChecker $status_checker */
    $status_checker = $this->container->get(StatusChecker::class);
    $last_results = $status_checker->getResults();
    $last_run_time = $status_checker->getLastRunTime();
    // Do not run status checks more than once an hour unless there are no
    // results available.
    $needs_run = $last_results === NULL || !$last_run_time || $time->getRequestTime() - $last_run_time > 3600;

    $settings = $this->container->get('config.factory')
      ->get('automatic_updates.settings')
      ->get('unattended');

    // To ensure consistent results, only run the status checks if we're
    // explicitly configured to do unattended updates on the command line.
    if ($needs_run && (($settings['method'] === 'web' && $this->stage->isFromWeb) || $settings['method'] === 'console')) {
      // Only send failure notifications if unattended updates are enabled.
      if ($settings['level'] !== CronUpdateRunner::DISABLED) {
        $this->container->get(StatusCheckMailer::class)
          ->sendFailureNotifications($last_results, $status_checker->run()->getResults());
      }
    }
  }

}
