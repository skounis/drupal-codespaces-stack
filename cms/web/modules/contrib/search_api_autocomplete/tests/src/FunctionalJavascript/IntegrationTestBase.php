<?php

namespace Drupal\Tests\search_api_autocomplete\FunctionalJavascript;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Provides a base class for integration tests of this module.
 */
abstract class IntegrationTestBase extends WebDriverTestBase {

  use PluginTestTrait;

  /**
   * The ID of the search entity created for this test.
   *
   * @var string
   */
  protected $searchId;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Retrieves autocomplete suggestions from a field on the current page.
   *
   * @param string $field_html_id
   *   (optional) The HTML ID of the field.
   * @param string $input
   *   (optional) The input to write into the field. The default contains
   *   uppercase characters and accents to verify input is properly
   *   preprocessed.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The suggestion elements from the page.
   */
  protected function getAutocompleteSuggestions($field_html_id = 'edit-keys', $input = 'Tést') {
    $page = $this->getSession()->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert_session */
    $assert_session = $this->assertSession();
    $field = $assert_session->elementExists('css', "input[data-drupal-selector=\"$field_html_id\"]");
    $field->setValue($input);
    $this->getSession()
      ->getDriver()
      ->keyDown($field->getXpath(), substr($input, -1));

    $element = $assert_session->waitOnAutocomplete();
    $this->assertTrue($element && $element->isVisible());
    $this->logPageChange();

    // Contrary to documentation, this can also return NULL. Therefore, we need
    // to make sure to return an array even in this case.
    return $page->findAll('css', '.ui-autocomplete .ui-menu-item') ?: [];
  }

  /**
   * Retrieves the text contents of a descendant of the given element.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element.
   * @param string $css_selector
   *   The CSS selector defining the descendant to look for.
   *
   * @return string|null
   *   The text contents of the descendant, or NULL if it couldn't be found.
   */
  protected function getElementText(NodeElement $element, $css_selector) {
    $element = $element->find('css', $css_selector);
    return $element ? $element->getText() : NULL;
  }

  /**
   * Returns the path of an admin page.
   *
   * @param string|null $page
   *   (optional) Either "edit" or "delete" to get the path of the respective
   *   search form, or NULL for the index's "Autocomplete" tab.
   * @param string|null $search_id
   *   (optional) The ID of the search to link to, if a page is specified. NULL
   *   to use the default search used by this test.
   *
   * @return string
   *   The internal path to the specified page.
   */
  protected function getAdminPath($page = NULL, $search_id = NULL) {
    $path = 'admin/config/search/search-api/index/autocomplete_search_index/autocomplete';
    if ($page !== NULL) {
      if ($search_id === NULL) {
        $search_id = $this->searchId;
      }
      $path .= "/$search_id/$page";
    }
    return $path;
  }

  /**
   * Logs a page change, if HTML output logging is enabled.
   *
   * The base class only logs requests when the drupalGet() or drupalPost()
   * methods are used, so we need to implement this ourselves for other page
   * changes.
   *
   * To enable HTML output logging, create some file where links to the logged
   * pages should be placed and set the "BROWSERTEST_OUTPUT_FILE" environment
   * variable to that file's path.
   *
   * @param string|null $url
   *   (optional) The URL requested, if not the current URL.
   * @param string $method
   *   (optional) The HTTP method used for the request.
   *
   * @see \Drupal\Tests\BrowserTestBase::drupalGet()
   * @see \Drupal\Tests\BrowserTestBase::setUp()
   */
  protected function logPageChange($url = NULL, $method = 'GET') {
    $session = $this->getSession();
    $driver = $session->getDriver();
    if (!$this->htmlOutputEnabled || $driver instanceof BrowserKitDriver) {
      return;
    }
    $current_url = $session->getCurrentUrl();
    $url = $url ?: $current_url;
    $html_output = "$method request to: $url<hr />Ending URL: $current_url";
    $html_output .= '<hr />' . $session->getPage()->getContent();;
    $html_output .= $this->getHtmlOutputHeaders();
    $this->htmlOutput($html_output);
  }

  /**
   * Asserts that the specified element exists and is visible.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown if the element doesn't exist.
   */
  protected function assertVisible($selector_type, $selector) {
    $element = $this->assertSession()->elementExists($selector_type, $selector);
    $this->assertTrue($element->isVisible(), "Element should be visible but isn't.");
  }

  /**
   * Asserts that the specified element exists but is not visible.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown if the element doesn't exist.
   */
  protected function assertNotVisible($selector_type, $selector) {
    $element = $this->assertSession()->elementExists($selector_type, $selector);
    $this->assertFalse($element->isVisible(), "Element shouldn't be visible but is.");
  }

}
