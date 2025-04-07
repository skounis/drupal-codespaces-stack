/**
 * @file
 * Expands the behavior of the default autocompletion.
 */

(($, Drupal, drupalSettings, once) => {
  // As a safety precaution, bail if the Drupal Core autocomplete framework is
  // not present.
  if (!Drupal.autocomplete) {
    return;
  }

  const autocomplete = {};

  /**
   * Retrieves the custom settings for an autocomplete-enabled input field.
   *
   * @param {HTMLElement} input
   *   The input field.
   * @param {object} globalSettings
   *   The object containing global settings. If none is passed, drupalSettings
   *   is used instead.
   *
   * @return {object}
   *   The effective settings for the given input fields, with defaults set if
   *   applicable.
   */
  autocomplete.getSettings = (input, globalSettings) => {
    globalSettings = globalSettings || drupalSettings || {};
    // Set defaults for all known settings.
    const settings = {
      auto_submit: false,
      delay: 0,
      min_length: 1,
      selector: ':submit',
    };
    const search = $(input).data('search-api-autocomplete-search');
    if (
      search &&
      globalSettings.search_api_autocomplete &&
      globalSettings.search_api_autocomplete[search]
    ) {
      $.extend(settings, globalSettings.search_api_autocomplete[search]);
    }
    return settings;
  };

  /**
   * Attaches our custom autocomplete settings to all affected fields.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the autocomplete behaviors.
   */
  Drupal.behaviors.searchApiAutocomplete = {
    attach(context, settings) {
      // Find all our fields with autocomplete settings.
      const s = '.ui-autocomplete-input[data-search-api-autocomplete-search]';
      $(once('search-api-autocomplete', s, context)).each(function foreach() {
        const uiAutocomplete = $(this).data('ui-autocomplete');
        if (!uiAutocomplete) {
          return;
        }
        const $element = uiAutocomplete.menu.element;
        $element.data('search-api-autocomplete-input-id', this.id);
        $element.addClass('search-api-autocomplete-search');
        $element.attr('tabindex', '-1');
        const elementSettings = autocomplete.getSettings(this, settings);
        if (elementSettings.delay) {
          uiAutocomplete.options.delay = elementSettings.delay;
        }
        if (elementSettings.min_length) {
          uiAutocomplete.options.minLength = elementSettings.min_length;
        }
        // Override the "select" callback of the jQuery UI autocomplete.
        const oldSelect = uiAutocomplete.options.select;
        uiAutocomplete.options.select = function select(event, ui, ...args) {
          // If this is a URL suggestion, instead of autocompleting we redirect
          // the user to that URL.
          if (ui.item.url) {
            if (event.keyCode === 9) {
              return false;
            }
            window.location.href = ui.item.url;
            return false;
          }

          const ret = oldSelect.apply(this, [event, ui, ...args]);

          // If auto-submit is enabled, submit the form.
          if (elementSettings.auto_submit && elementSettings.selector) {
            $(elementSettings.selector, this.form).trigger('click');
          }

          return ret;
        };
      });
    },
  };

  Drupal.SearchApiAutocomplete = autocomplete;
})(jQuery, Drupal, drupalSettings, once);
