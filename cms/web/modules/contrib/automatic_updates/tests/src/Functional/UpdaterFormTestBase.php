<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Functional;

use Drupal\automatic_updates_test\EventSubscriber\TestSubscriber1;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\package_manager\Event\StatusCheckEvent;
use Drupal\package_manager\ValidationResult;
use Drupal\system\SystemManager;
use Drupal\Tests\automatic_updates\Traits\ValidationTestTrait;
use Drupal\Tests\package_manager\Traits\PackageManagerBypassTestTrait;

/**
 * Base class for functional tests of updater form.
 *
 * @internal
 */
abstract class UpdaterFormTestBase extends AutomaticUpdatesFunctionalTestBase {

  use PackageManagerBypassTestTrait;
  use ValidationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'automatic_updates',
    'automatic_updates_test',
    'help',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    static::$errorsExplanation = 'Your site cannot be automatically updated until further action is performed.';
    parent::setUp();

    $this->setReleaseMetadata(__DIR__ . '/../../../package_manager/tests/fixtures/release-history/drupal.9.8.1-security.xml');
    $permissions = [
      'administer site configuration',
      'administer software updates',
      'access administration pages',
      'access site in maintenance mode',
      'administer modules',
      'access site reports',
      'view update notifications',
      // CORE_MR_ONLY:'access help pages',
    ];
    // BEGIN: DELETE FROM CORE MERGE REQUEST
    if (array_key_exists('access help pages', $this->container->get('user.permissions')->getPermissions())) {
      $permissions[] = 'access help pages';
    }
    // END: DELETE FROM CORE MERGE REQUEST
    $user = $this->createUser($permissions);
    $this->drupalLogin($user);
    $this->checkForUpdates();
  }

  /**
   * Asserts that no update buttons exist.
   */
  protected function assertNoUpdateButtons(): void {
    $this->assertSession()->elementNotExists('css', "input[value*='Update']");
  }

  /**
   * Sets an error message, runs status checks, and asserts it is displayed.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The cached error check message.
   */
  protected function setAndAssertCachedMessage(): TranslatableMarkup {
    // Store a status error, which will be cached.
    $message = t("You've not experienced Shakespeare until you have read him in the original Klingon.");
    $result = ValidationResult::createError([$message]);
    TestSubscriber1::setTestResult([$result], StatusCheckEvent::class);
    // Run the status checks a visit an admin page the message will be
    // displayed.
    $this->drupalGet('/admin/reports/status');
    $this->clickLink('Rerun readiness checks');
    $this->drupalGet('/admin');
    $this->assertSession()->pageTextContains($message->render());
    // Clear the results so the only way the message could appear on the pages
    // used for the update process is if they show the cached results.
    TestSubscriber1::setTestResult(NULL, StatusCheckEvent::class);

    return $message;
  }

  /**
   * Checks the table for a release on the form.
   *
   * @param string $container_locator
   *   The CSS locator for the element with contains the table.
   * @param string $row_class
   *   The row class for the update.
   * @param string $version
   *   The release version number.
   * @param bool $is_primary
   *   Whether update button should be a primary button.
   * @param string|null $table_caption
   *   The table caption or NULL if none expected.
   */
  protected function checkReleaseTable(string $container_locator, string $row_class, string $version, bool $is_primary, ?string $table_caption = NULL): void {
    $assert_session = $this->assertSession();
    $assert_session->pageTextNotContains('There is a security update available for your version of Drupal.');
    $assert_session->linkExists('Drupal core');
    $container = $assert_session->elementExists('css', $container_locator);
    if ($table_caption) {
      $this->assertSame($table_caption, $assert_session->elementExists('css', 'caption', $container)->getText());
    }
    else {
      $assert_session->elementNotExists('css', 'caption', $container);
    }

    $cells = $assert_session->elementExists('css', $row_class, $container)
      ->findAll('css', 'td');
    $this->assertCount(2, $cells);
    $this->assertSame("$version (Release notes)", $cells[1]->getText());
    $release_notes = $assert_session->elementExists('named', ['link', 'Release notes'], $cells[1]);
    $this->assertSame("Release notes for Drupal core $version", $release_notes->getAttribute('title'));
    $button = $assert_session->buttonExists("Update to $version", $container);
    $this->assertSame($is_primary, $button->hasClass('button--primary'));
  }

  /**
   * Asserts that a status message containing a given validation result exists.
   *
   * @param \Drupal\package_manager\ValidationResult $result
   *   A validation result.
   */
  protected function assertStatusMessageContainsResult(ValidationResult $result): void {
    $assert_session = $this->assertSession();
    $type = $result->severity === SystemManager::REQUIREMENT_ERROR ? 'error' : 'warning';
    $assert_session->statusMessageContains((string) $result->summary, $type);
    $assert_session->pageTextContainsOnce((string) $result->summary);
    foreach ($result->messages as $message) {
      $assert_session->statusMessageContains((string) $message, $type);
      $assert_session->pageTextContainsOnce((string) $message);
    }
  }

}
