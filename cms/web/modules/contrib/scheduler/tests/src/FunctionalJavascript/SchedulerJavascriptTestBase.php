<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\DocumentElement;
use Drupal\Tests\scheduler\Traits\SchedulerCommerceProductSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerTaxonomyTermSetupTrait;

/**
 * Base class for Scheduler javascript tests.
 */
abstract class SchedulerJavascriptTestBase extends WebDriverTestBase {

  use SchedulerCommerceProductSetupTrait;
  use SchedulerMediaSetupTrait;
  use SchedulerSetupTrait;
  use SchedulerTaxonomyTermSetupTrait;

  /**
   * The standard modules to load for all javascript tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = [
    'scheduler',
    'media',
    'commerce_product',
    'taxonomy',
  ];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * The default theme.
   *
   * The vertical tabs test needs 'claro' theme not 'stark'.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Call the common set-up functions defined in the traits.
    $this->schedulerSetUp();
    // $this->toString() includes the test class and the dataProvider key.
    // We can use this to save time and resources by avoiding calls to the
    // entity-specific setup functions when they are not needed.
    $testName = $this->toString();
    if (stristr($testName, 'media')) {
      $this->schedulerMediaSetUp();
    }
    if (stristr($testName, 'product')) {
      $this->SchedulerCommerceProductSetUp();
    }
    if (stristr($testName, 'taxonomy')) {
      $this->SchedulerTaxonomyTermSetup();
    }
  }

  /**
   * Flush cache.
   */
  protected function flushCache() {
    $module_handler = \Drupal::moduleHandler();
    $module_handler->invokeAll('cache_flush');
  }

  /**
   * Looks for the specified text and returns TRUE when it is unavailable.
   *
   * Core JSWebAssert has a function waitForText() but there is no equivalent to
   * wait until text is hidden, as there is for some other page elements.
   * Therefore define that function here, based on waitForText() in
   * core/tests/Drupal/FunctionalJavascriptTests/JSWebAssert.php.
   *
   * @param string $text
   *   The text to wait for.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return bool
   *   TRUE if not found, FALSE if found.
   */
  public function waitForNoText($text, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    return (bool) $page->waitFor($timeout / 1000, function (DocumentElement $page) use ($text) {
      $actual = preg_replace('/\\s+/u', ' ', $page->getText());
      // Negative look-ahead on the text that should be hidden.
      $regex = '/^((?!' . preg_quote($text, '/') . ').)*$/ui';
      return (bool) preg_match($regex, $actual);
    });
  }

}
