<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\scheduler\Traits\SchedulerCommerceProductSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerMediaSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;
use Drupal\Tests\scheduler\Traits\SchedulerTaxonomyTermSetupTrait;

/**
 * Base class to provide common browser test setup.
 */
abstract class SchedulerBrowserTestBase extends BrowserTestBase {

  use SchedulerCommerceProductSetupTrait;
  use SchedulerMediaSetupTrait;
  use SchedulerSetupTrait;
  use SchedulerTaxonomyTermSetupTrait;

  /**
   * The standard modules to load for all browser tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = [
    'scheduler',
    'dblog',
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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Call the common set-up functions defined in the traits.
    $this->schedulerSetUp();
    // $this->toString() includes the test class and the dataProvider key.
    // We can use this to save time and resources by avoiding calls to the
    // entity-specific setup functions when they are not needed. The exception
    // is the permission tests, which use all entities for all tests.
    $testName = $this->toString();
    if (stristr($testName, 'media') || stristr($testName, 'permission')) {
      $this->schedulerMediaSetUp();
    }
    if (stristr($testName, 'product') || stristr($testName, 'permission')) {
      $this->SchedulerCommerceProductSetUp();
    }
    if (stristr($testName, 'taxonomy') || stristr($testName, 'permission')) {
      $this->SchedulerTaxonomyTermSetup();
    }
  }

}
