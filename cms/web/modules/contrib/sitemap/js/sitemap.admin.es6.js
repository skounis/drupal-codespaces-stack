/**
 * @file
 * Attaches administration-specific behavior for the Sitemap module.
 */

(function ($, Drupal) {
  /**
   * Displays and updates the status of plugins on the admin page.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behaviors to the sitemap admin form.
   */
  Drupal.behaviors.sitemapStatus = {
    attach(context) {
      const $context = $(context);
      $(
        once(
          'sitemap-enabled',
          $context.find('#sitemap-enabled-wrapper input.form-checkbox'),
          context,
        ),
      ).each(function () {
        const $checkbox = $(this);
        // Retrieve the tabledrag row belonging to this filter.
        const $row = $context
          .find(`#${$checkbox.attr('id').replace(/-enabled$/, '-weight')}`)
          .closest('tr');
        // Retrieve the vertical tab belonging to this filter.
        const $filterSettings = $context.find(
          `#${$checkbox.attr('id').replace(/-enabled$/, '-settings')}`,
        );
        const filterSettingsTab = $filterSettings.data('verticalTab');

        // Bind click handler to this checkbox to conditionally show and hide
        // the filter's tableDrag row and vertical tab pane.
        $checkbox.on('click.filterUpdate', () => {
          if ($checkbox.is(':checked')) {
            $row.show();
            if (filterSettingsTab) {
              filterSettingsTab.tabShow().updateSummary();
            } else {
              // On very narrow viewports, Vertical Tabs are disabled.
              $filterSettings.show();
            }
          } else {
            $row.hide();
            if (filterSettingsTab) {
              filterSettingsTab.tabHide().updateSummary();
            } else {
              // On very narrow viewports, Vertical Tabs are disabled.
              $filterSettings.hide();
            }
          }
          // Restripe table after toggling visibility of table row.
          Drupal.tableDrag['sitemap-order'].restripeTable();
        });

        // Attach summary for configurable filters (only for screen readers).
        if (filterSettingsTab) {
          filterSettingsTab.details.drupalSetSummary(() =>
            $checkbox.is(':checked')
              ? Drupal.t('Enabled')
              : Drupal.t('Disabled'),
          );
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.filterUpdate');
      });
    },
  };
})(jQuery, Drupal);
