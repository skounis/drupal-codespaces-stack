/**
 * @file
 * Attaches behaviors for the Simple XML Sitemap display extender.
 */

((Drupal, once) => {
  /**
   * The behavior of the indexed arguments.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to the indexed arguments.
   */
  Drupal.behaviors.simpleSitemapViewsUiArguments = {
    attach(context) {
      once(
        'simple-sitemap-views-ui-arguments',
        'fieldset.indexed-arguments',
        context,
      ).forEach((element) => {
        let checkboxes = element.querySelectorAll('input[type="checkbox"]');
        checkboxes = Array.from(checkboxes);

        /**
         * Mark all checkboxes above the current one as checked.
         *
         * @param {number} index
         *   The index of the current checkbox.
         */
        const check = (index) => {
          checkboxes.slice(0, index).forEach((checkbox) => {
            checkbox.checked = true;
          });
        };

        /**
         * Mark all checkboxes below the current one as unchecked.
         *
         * @param {number} index
         *   The index of the current checkbox.
         */
        const uncheck = (index) => {
          checkboxes.slice(index).forEach((checkbox) => {
            checkbox.checked = false;
          });
        };

        checkboxes.forEach((checkbox) => {
          checkbox.addEventListener('change', () => {
            const index = checkboxes.indexOf(checkbox);

            if (checkbox.checked) {
              check(index);
            } else {
              uncheck(index);
            }
          });
        });
      });
    },
  };
})(Drupal, once);
