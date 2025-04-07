<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Helpers for the analysis widget.
 */
class Widget extends RawMinkContext {

  /**
   * Check that the RTSEO widget was updated.
   *
   * @When (I )wait for the widget to be updated
   */
  public function assertWidgetUpdated() : void {
    // The widget will not update when the user is doing something since that
    // may cause an update for every keystroke. Instead we wait for form fields
    // to become unfocused after they change.
    $this->getSession()->executeScript("document.activeElement.blur()");

    // Ensure there was some AJAX request and the widget has output.
    if (!$this->getSession()->getDriver()->wait(2000, 'document.querySelectorAll(".yoast-seo-preview-submit-button .ajax-progress").length === 0 && window["yoast-output"].children.length > 0')) {
      throw new \RuntimeException("Widget AJAX did not complete or the widget didn't produce output");
    }
  }

}
