/**
 * @file
 * ECA models listing behaviors.
 */

(function ($, Drupal) {
  /**
   * Filters the ECA model listing tables by a text input search string.
   *
   * Text search input: input.models-filter-text
   * Target table:      input.models-filter-text[data-table]
   * Source text:       models-table-filter-text-source
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the filter functionality to the models admin text search field.
   */
  Drupal.behaviors.ecaTableFilterByText = {
    attach(context, settings) {
      $('ul.eca-event-list li:nth-of-type(4)').click(function () {
        const parent = $(this).parent();
        $(parent).removeClass('eca-event-list');
      });
      const [input] = once('models-filter-text', 'input.models-filter-text');
      if (!input) {
        return;
      }
      const $table = $(input.getAttribute('data-table'));
      let $rows;

      function filterModelList(e) {
        const query = e.target.value.toLowerCase();

        function showModelRow(index, row) {
          const sources = row.querySelectorAll(
            '[data-drupal-selector="models-table-filter-text-source"]',
          );
          let sourcesConcat = '';
          sources.forEach((item) => {
            sourcesConcat += item.textContent;
          });
          const textMatch = sourcesConcat.toLowerCase().indexOf(query) !== -1;
          $(row).closest('tr').toggle(textMatch);
        }

        // Filter if the length of the query is at least 2 characters.
        if (query.length >= 2) {
          $rows.each(showModelRow);
        } else {
          $rows.show();
        }
      }

      if ($table.length) {
        $rows = $table.find('tbody tr');
        $(input).on('keyup', filterModelList);
      }
    },
  };
})(jQuery, Drupal);
