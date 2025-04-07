<?php

declare(strict_types=1);

namespace Drupal\Tests\automatic_updates\Build;

use Drupal\Component\Utility\Html;
use Drupal\Tests\package_manager\Build\TemplateProjectTestBase;

/**
 * Base class for tests that perform in-place updates.
 *
 * @internal
 */
abstract class UpdateTestBase extends TemplateProjectTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createTestProject(string $template): void {
    parent::createTestProject($template);
    // @todo Remove in https://www.drupal.org/project/automatic_updates/issues/3284443
    $code = <<<END
\$config['automatic_updates.settings']['unattended']['level'] = 'security';
END;
    $this->writeSettings($code);
    // Install Automatic Updates, and other modules needed for testing.
    $this->installModules([
      'automatic_updates',
      'automatic_updates_test_api',
    ]);

    // Uninstall Automated Cron because this will run cron updates on most
    // requests, making it difficult to test other forms of updating.
    // Also uninstall Big Pipe, since it may cause page elements to be rendered
    // in the background and replaced with JavaScript, which isn't supported in
    // build tests.
    // @see \Drupal\Tests\automatic_updates\Build\CoreUpdateTest::testAutomatedCron
    $page = $this->getMink()->getSession()->getPage();
    $this->visit('/admin/modules/uninstall');
    $page->checkField("uninstall[automated_cron]");
    $page->checkField('uninstall[big_pipe]');
    $page->pressButton('Uninstall');
    $page->pressButton('Uninstall');
  }

  /**
   * Checks for available updates.
   *
   * Assumes that a user with the appropriate access is logged in.
   */
  protected function checkForUpdates(): void {
    $this->visit('/admin/reports/updates');
    $this->getMink()->getSession()->getPage()->clickLink('Check manually');
    $this->waitForBatchJob();
  }

  /**
   * Waits for an active batch job to finish.
   */
  protected function waitForBatchJob(): void {
    $refresh = $this->getMink()
      ->getSession()
      ->getPage()
      ->find('css', 'meta[http-equiv="Refresh"], meta[http-equiv="refresh"]');

    if ($refresh) {
      // Parse the content attribute of the meta tag for the format:
      // "[delay]: URL=[page_to_redirect_to]".
      if (preg_match('/\d+;\s*URL=\'?(?<url>[^\']*)/i', $refresh->getAttribute('content'), $match)) {
        $url = Html::decodeEntities($match['url']);
        $this->visit($url);
        $this->waitForBatchJob();
      }
    }
  }

  /**
   * Asserts the status report does not have any readiness errors or warnings.
   */
  protected function assertStatusReportChecksSuccessful(): void {
    $this->visit('/admin/reports/status');
    $mink = $this->getMink();
    $page = $mink->getSession()->getPage();
    $page->clickLink('Rerun readiness checks');

    $readiness_check_summaries = $page->findAll('css', '*:contains("Update readiness checks")');
    // There should always either be the summary section indicating the site is
    // ready for automatic updates or the error or warning sections.
    $this->assertNotEmpty($readiness_check_summaries);
    $ready_text_found = FALSE;
    $status_checks_text = '';
    foreach ($readiness_check_summaries as $readiness_check_summary) {
      $parent_element = $readiness_check_summary->getParent();
      if (str_contains($parent_element->getText(), 'Your site is ready for automatic updates.')) {
        $ready_text_found = TRUE;
        continue;
      }
      $description_list = $parent_element->find('css', 'ul');
      $this->assertNotEmpty($description_list);
      $status_checks_text .= "\n" . $description_list->getText();
    }
    $this->assertSame('', $status_checks_text);
    $this->assertTrue($ready_text_found);
  }

}
