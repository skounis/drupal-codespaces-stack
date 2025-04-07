<?php

// @codingStandardsIgnoreFile

use Drupal\Component\Utility\Timer;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simple_sitemap\Queue\BatchTrait;
use Drupal\Tests\RandomGeneratorTrait;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Measures sitemap generation performance by running the generation process 4 times
 * while measuring memory usage and the number of queries performed.
 *
 * The command can be used on a production site or can generate content for testing.
 *
 * Usage:
 * - drush scr --uri http://example.com modules/simple_sitemap/tests/scripts/performance_test.php
 *
 * Generate content prior to test:
 * - drush scr --uri http://example.com modules/simple_sitemap/tests/scripts/performance_test.php -- generate 500
 */

if  (PHP_SAPI !== 'cli') {
  exit;
}

if (!function_exists('drush_backend_batch_process')) {
  throw new \RuntimeException("This script is designed to be run with drush scr");
}

include_once 'core/tests/Drupal/Tests/RandomGeneratorTrait.php';

$module_handler = \Drupal::moduleHandler();
/** @var \Psr\Log\LoggerInterface $logger */
$logger = Drush::service('logger');
if (!(\Drupal::moduleHandler()->moduleExists('simple_sitemap'))) {
  $logger->error("In order to use this script, simple_sitemap must be installed.");
  exit;
}

class Tester {

  /**
   * @var int
   */
  private $timerKey = 0;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  use RandomGeneratorTrait;

  public function createNodeType() {
    if (NodeType::load('simple_sitemap_performance_test')) {
      return;
    }
    // Create the content type.
    $node_type = NodeType::create([
      'type' => 'simple_sitemap_performance_test',
      'name' => 'simple_sitemap_performance_test',
    ]);
    $node_type->save();
    node_add_body_field($node_type);

    /** @var \Drupal\simple_sitemap\Manager\Generator $generator */
    $generator = \Drupal::service('simple_sitemap.generator');
    $generator->entityManager()->setBundleSettings('node', 'simple_sitemap_performance_test', [
        'index' => TRUE,
    ]);
  }

  public function createNode() {
    // Create a node.
    $node = Node::create([
      'type' => 'simple_sitemap_performance_test',
      'title' => $this->getRandomGenerator()->sentences(5),
      'body' => $this->getRandomGenerator()->sentences(20),
    ]);
    $node->save();
  }

  public function runGenerate($count_queries = FALSE) {
    $batch = new BatchBuilder();
    $relative_path_to_script = (new Filesystem())->makePathRelative(__DIR__, \Drupal::root()) . basename(__FILE__);
    $batch->setFile($relative_path_to_script);
    $batch->addOperation([static::class, 'doBatchGenerate'], [$count_queries]);
    $batch->setFinishCallback([BatchTrait::class, 'finishGeneration']);

    // Start drush batch process.
    batch_set($batch->toArray());

    // See https://www.drupal.org/node/638712
    $batch =& batch_get();
    $batch['progressive'] = FALSE;

    $timer = 'simple_sitemap:perf_test:' . $this->timerKey++;
    Timer::start($timer);
    drush_backend_batch_process();
    $time = round(Timer::stop($timer)['time'] / 1000, 2) . ' seconds';
    $this->logger->info('Generation completed in: ' . $time);

    // Remove the batch as Drush doesn't appear to properly clean up on success.
    $batch =& batch_get();
    $batch = NULL;
  }

  /**
   * @param bool $count_queries
   * @param $context
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function doBatchGenerate($count_queries, &$context) {
    if ($count_queries) {
      $query_logger = Database::startLog('simple_sitemap');
    }
    // Passes a special object in to $context that outputs every time
    // $context['message'] is set.
    BatchTrait::doBatchGenerate($context);
    if ($count_queries) {
      $context['message'] = "Query count: " . count($query_logger->get('simple_sitemap'));
    }
    else {
      $peak_mem = ByteSizeMarkup::create(memory_get_peak_usage(TRUE));
      $mem = ByteSizeMarkup::create(memory_get_usage(TRUE));
      $context['message'] = "Memory: $peak_mem, non-peak mem: $mem";
    }
  }

}

$batch =& batch_get();
if (!empty($batch['running'])) {
  // We're in the batch. Nothing to do.
  return;
}

$tester = new Tester($logger);
// Ensure the messages are seen.
if (!Drush::output()->isVerbose()) {
  Drush::output()->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
}

// Generate content if the generate argument is passed to the command.
if (isset($extra[0]) && strtolower($extra[0]) === 'generate') {
  $tester->createNodeType();
  $node_count = isset($extra[1]) ? (int) $extra[1]: 500;
  for ($i = 0; $i < $node_count; $i++) {
    $tester->createNode();
  }
  $logger->info("Created $node_count nodes for sitemap testing");
}

// Memory tests.
$logger->info("Running memory usage tests:");
drupal_flush_all_caches();
// Run 1.
$tester->runGenerate();
// Run 2.
$tester->runGenerate();

// SQL queries tests.
$logger->info("Running query count tests:");
drupal_flush_all_caches();
// Run 1.
$tester->runGenerate(TRUE);
// Run 2.
$tester->runGenerate(TRUE);
