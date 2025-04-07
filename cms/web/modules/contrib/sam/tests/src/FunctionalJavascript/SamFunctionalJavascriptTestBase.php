<?php

namespace Drupal\Tests\sam\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for Simple Add More Functional Javascript tests.
 *
 * @group sam
 */
abstract class SamFunctionalJavascriptTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'link',
    'field',
    'field_ui',
    'node',
    'system',
    'sam',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The node type under test.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Place some blocks to make our lives easier down the road.
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create a node type and link field.
    $this->nodeType = $this->drupalCreateContentType([
      'type' => 'node_type',
      'name' => 'Node Type',
    ]);
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_node__link',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => 3,
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => $this->nodeType->id(),
      'field_name' => 'field_node__link',
      'entity_type' => 'node',
      'label' => 'Link Field',
    ])->save();
    // Add the field to the form.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', $this->nodeType->id(), 'default')
      ->setComponent('field_node__link', [
        'type' => 'link_default',
      ])
      ->save();
  }

  /**
   * Asserts that text appears on page after a wait.
   *
   * @param string $text
   *   The text that should appear on the page.
   * @param int $timeout
   *   Timeout in milliseconds, defaults to 10000.
   */
  protected function waitForText($text, $timeout = 10000) {
    $result = $this->assertSession()->waitForText($text, $timeout);
    $this->assertNotEmpty($result, "\"$text\" not found");
  }

  /**
   * Asserts that text does not appear on page after a wait.
   *
   * @param string $text
   *   The text that should not be on the page.
   * @param int $timeout
   *   Timeout in milliseconds, defaults to 10000.
   */
  protected function waitForNoText($text, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    $result = $page->waitFor($timeout / 1000, function ($page) use ($text) {
      $actual = preg_replace('/\s+/u', ' ', $page->getText());
      $regex = '/' . preg_quote($text, '/') . '/ui';
      return (bool) !preg_match($regex, $actual);
    });
    $this->assertNotEmpty($result, "\"$text\" was found but shouldn't be there.");
  }

  /**
   * Waits for the specified selector and returns it if not empty.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   Timeout in milliseconds, defaults to 10000.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The page element node if found. If not found, the test fails.
   */
  protected function assertElementExistsAfterWait($selector, $locator, $timeout = 10000) {
    $element = $this->assertSession()->waitForElement($selector, $locator, $timeout);
    $this->assertNotEmpty($element);
    return $element;
  }

  /**
   * Debugger method to save additional HTML output.
   *
   * The base class will only save browser output when accessing page using
   * ::drupalGet and providing a printer class to PHPUnit. This method
   * is intended for developers to help debug browser test failures and capture
   * more verbose output.
   */
  protected function saveHtmlOutput() {
    $out = $this->getSession()->getPage()->getContent();
    // Ensure that any changes to variables in the other thread are picked up.
    $this->refreshVariables();
    if ($this->htmlOutputEnabled) {
      $html_output = '<hr />Ending URL: ' . $this->getSession()->getCurrentUrl();
      $html_output .= '<hr />' . $out;
      $html_output .= $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
  }

}
