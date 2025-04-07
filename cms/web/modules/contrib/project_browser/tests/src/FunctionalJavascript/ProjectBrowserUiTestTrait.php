<?php

declare(strict_types=1);

namespace Drupal\Tests\project_browser\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;

/**
 * Trait used by UI tests for testing actions like clicking and dragging.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
trait ProjectBrowserUiTestTrait {

  /**
   * Waits for specific text to appear on the page.
   *
   * @param string $text
   *   The text we're waiting for.
   */
  protected function assertPageHasText(string $text): void {
    $this->assertTrue($this->assertSession()->waitForText($text), "Expected '$text' to appear on the page but it didn't.");
  }

  /**
   * Waits for an element to be visible, and returns it.
   *
   * @param mixed ...$arguments
   *   Arguments to pass to JSWebAssert::waitForElementVisible().
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element we were waiting for.
   */
  protected function assertElementIsVisible(mixed ...$arguments): NodeElement {
    $element = $this->assertSession()->waitForElementVisible(...$arguments);
    $this->assertInstanceOf(NodeElement::class, $element);
    return $element;
  }

  /**
   * Installs a specific project.
   *
   * @param \Behat\Mink\Element\NodeElement|string $card
   *   The project's card element, or its exact name.
   * @param int $timeout
   *   (optional) How many seconds to wait for the installation to finish.
   *   Defaults to 30.
   * @param bool $multiple_instance
   *   (optional) When TRUE, multiple instances are enabled.
   *
   * @see ::waitForProject()
   * @see ::waitForProjectToBeInstalled()
   */
  protected function installProject(NodeElement|string $card, int $timeout = 30, bool $multiple_instance = FALSE): void {
    if (is_string($card)) {
      $card = $this->waitForProject($card);
    }
    $name = $card->find('css', '.pb-project__title')?->getText();
    $this->assertNotEmpty($name);
    if ($multiple_instance) {
      $card->pressButton("Select $name");
      $card->waitFor(10, fn ($card) => $card->hasButton("Deselect $name"));
      $this->getSession()->getPage()->pressButton('Install selected projects');
    }
    else {
      $card->pressButton("Install $name");
    }
    $this->waitForProjectToBeInstalled($card, $timeout);
  }

  /**
   * Waits for a specific project to be installed.
   *
   * @param \Behat\Mink\Element\NodeElement|string $card
   *   The project's card element, or its exact name.
   * @param int $timeout
   *   (optional) How many seconds to wait for the installation to finish.
   *   Defaults to 30.
   *
   * @see ::waitForProject()
   */
  protected function waitForProjectToBeInstalled(NodeElement|string $card, int $timeout = 30): void {
    if (is_string($card)) {
      $card = $this->waitForProject($card);
    }
    $name = $card->find('css', '.pb-project__title')?->getText();
    $this->assertNotEmpty($name);

    $indicator = $card->waitFor(
      $timeout,
      fn (NodeElement $card): ?NodeElement => $card->find('css', '.project_status-indicator'),
    );
    $was_installed = $indicator?->waitFor(
      $timeout,
      fn (NodeElement $indicator) => $indicator->getText() === "$name is Installed",
    );
    $this->assertTrue($was_installed, "$name was not installed after waiting $timeout seconds.");
  }

  /**
   * Waits for a project card to appear, and returns it.
   *
   * @param string $name
   *   The full human-readable name of the project as it appears in the UI.
   * @param int $timeout
   *   (optional) How many seconds to wait for the project to appear. Defaults
   *   to 10.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The project card element.
   */
  protected function waitForProject(string $name, int $timeout = 10): NodeElement {
    $element = $this->assertSession()
      ->waitForElementVisible('css', ".pb-project__title:contains('$name')", $timeout * 1000)
      ?->find('xpath', '..')
      ?->find('xpath', '..');
    $this->assertNotEmpty($element);
    return $element;
  }

  /**
   * Asserts that a table row element was dragged to another spot in the table.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The table row element.
   * @param int|float $timeout
   *   (int) How long to wait before timing out. Defaults to 10 seconds.
   */
  protected function assertTableRowWasDragged(NodeElement $element, int|float $timeout = 10): void {
    $indicator = $element->waitFor($timeout, function (NodeElement $element): ?NodeElement {
      return $element->find('css', '.tabledrag-changed');
    });
    $this->assertInstanceOf(NodeElement::class, $indicator);
  }

  /**
   * Searches for a term in the search field.
   *
   * @param string $value
   *   The value to search for.
   * @param bool $bypass_wait
   *   When TRUE, do not wait for a rerender after entering a search string.
   */
  protected function inputSearchField(string $value, bool $bypass_wait = FALSE): void {
    $search_field = $this->assertSession()->waitForElementVisible('css', '#pb-text');
    if ($bypass_wait) {
      $search_field->setValue($value);
    }
    else {
      $this->preFilterWait();
      $search_field->setValue($value);
      $this->postFilterWait();
    }
  }

  /**
   * Opens the advanced filter element.
   */
  protected function openAdvancedFilter(): void {
    $filter_icon_selector = $this->getSession()->getPage()->find('css', '.search__filter__toggle');
    $filter_icon_selector?->click();
    $this->assertSession()->waitForElementVisible('css', '.search__filter__toggle[aria-expanded="true"]');
  }

  /**
   * Changes the sort by field.
   *
   * @param string $value
   *   The value to sort by.
   * @param bool $bypass_wait
   *   When TRUE, do not wait for a rerender after entering a search string.
   */
  protected function sortBy(string $value, bool $bypass_wait = FALSE): void {
    if ($bypass_wait) {
      $this->getSession()->getPage()->selectFieldOption('pb-sort', $value);
    }
    else {
      $this->preFilterWait();
      $this->getSession()->getPage()->selectFieldOption('pb-sort', $value);
      $this->postFilterWait();
    }
  }

  /**
   * Add an attribute to a project card that will vanish after filtering.
   */
  protected function preFilterWait(): void {
    $this->getSession()->executeScript("document.querySelectorAll('.pb-project').forEach((project) => project.setAttribute('data-pre-filter', 'true'))");
  }

  /**
   * Confirm the attribute added in preFilterWait() is no longer present.
   */
  protected function postFilterWait(): void {
    $this->assertSession()->assertNoElementAfterWait('css', '[data-pre-filter]');
  }

  /**
   * Confirms Svelte initialized and will re-try once if not.
   *
   * In ~1% of GitLabCI tests, Svelte will not initialize. Since this difficulty
   * initializing is specific to GitLabCI and a refresh consistently fixes it,
   * we do an initial check and refresh when it fails.
   *
   * @param string $check_type
   *   The type of check to make (css or text)
   * @param string $check_value
   *   The value to check for.
   * @param int $timeout
   *   Timeout in milliseconds, defaults to 10000.
   */
  protected function svelteInitHelper(string $check_type, string $check_value, int $timeout = 10000): void {
    if ($check_type === 'css') {
      if (!$this->assertSession()->waitForElement('css', $check_value, $timeout)) {
        $this->getSession()->reload();
        $this->assertNotNull($this->assertSession()->waitForElement('css', $check_value, $timeout), 'Svelte did not initialize. Markup: ' . $this->getSession()->evaluateScript('document.querySelector("#project-browser").innerHTML'));
      }
    }
    if ($check_type === 'text') {
      if (!$this->assertSession()->waitForText($check_value, $timeout)) {
        $this->getSession()->reload();
        $this->assertTrue($this->assertSession()->waitForText($check_value, $timeout), 'Svelte did not initialize. Markup: ' . $this->getSession()->evaluateScript('document.querySelector("#project-browser").innerHTML'));
      }
    }
  }

  /**
   * Retrieves element text with JavaScript.
   *
   * This is an alternative for accessing element text with `getText()` in PHP.
   * Use this for elements that might become "stale element references" due to
   * re-rendering.
   *
   * @param string $selector
   *   CSS selector of the element.
   *
   * @return string
   *   The trimmed text content of the element.
   */
  protected function getElementText(string $selector): string {
    return trim($this->getSession()->evaluateScript("document.querySelector('$selector').textContent"));
  }

  /**
   * Asserts that a given list of pager items are present on the page.
   *
   * @param array $pager_items
   *   An array of expected pager item labels.
   */
  protected function assertPagerItems(array $pager_items): void {
    $page = $this->getSession()->getPage();

    $this->assertElementIsVisible('css', '#project-browser .pb-project');
    $items = array_map(function ($element) {
      return $element->getText();
    }, $page->findAll('css', '#project-browser .pager__item'));

    // There are two pagers, one on top and one at the bottom.
    $items = array_unique($items);
    $this->assertSame($pager_items, $items);
  }

  /**
   * Helper to wait for a field to appear on the page.
   *
   * @param string $locator
   *   The locator to use to find the field.
   * @param \Behat\Mink\Element\NodeElement|null $container
   *   The container to look within.
   */
  protected function waitForField(string $locator, ?NodeElement $container = NULL): ?NodeElement {
    $container ??= $this->getSession()->getPage();
    $this->assertTrue(
      $container->waitFor(10, fn ($container) => $container->findField($locator)?->isVisible()),
    );
    return $container->findField($locator);
  }

  /**
   * Helper to wait for text in a specific element.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element to look within.
   * @param string $text
   *   The text to look for.
   */
  protected function waitForElementToContainText(NodeElement $element, string $text): void {
    $this->assertTrue(
      $element->waitFor(10, fn ($element) => str_contains($element->getText(), $text)),
    );
  }

  /**
   * Returns the currently selected category names.
   *
   * @return string[]
   *   The names of the currently selected categories, in the order they appear
   *   as lozenges in the search area.
   */
  protected function getSelectedCategories(): array {
    $elements = $this->getSession()
      ->getPage()
      ->findAll('css', 'p.filter-applied .filter-applied__label');

    return array_map(fn (NodeElement $element) => $element->getText(), $elements);
  }

}
