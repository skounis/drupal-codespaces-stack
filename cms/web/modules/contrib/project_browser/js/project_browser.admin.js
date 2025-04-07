/**
 * @file
 * Tabledrag behaviors.
 *
 * This code is borrowed from block's JavaScript.
 *
 * @see core/modules/block/js/block.es6.js
 */

(function enableDisablePlugin($, window, Drupal, once) {
  /**
   * Enable/Disable a Plugin in the table via select list.
   *
   * This behavior is dependent on the tableDrag behavior, since it uses the
   * objects initialized in that behavior to update the row.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the tableDrag behavior plugin settings form.
   */
  Drupal.behaviors.projectBrowserPluginSourceDrag = {
    attach(context) {
      // Only proceed if tableDrag is present and we are on the settings page.
      if (!Drupal.tableDrag || !Drupal.tableDrag.project_browser) {
        return;
      }

      /**
       * Function to update the last placed row with the correct classes.
       *
       * @param {jQuery} table
       *   The jQuery object representing the table to inspect.
       * @param {jQuery} rowObject
       *   The jQuery object representing the table row.
       */
      function updateLastPlaced(table, rowObject) {
        const $rowObject = $(rowObject);
        // eslint-disable-next-line
        if (!$rowObject.is('.drag-previous')) {
          table.find('.drag-previous').removeClass('drag-previous');
          $rowObject.addClass('drag-previous');
        }
      }

      /**
       * Update source plugin weights in the given region.
       *
       * @param {jQuery} table
       *   Table with draggable items.
       * @param {string} region
       *   Machine name of region containing source plugin to update.
       */
      function updateSourcePluginWeights(table, region) {
        // Calculate minimum weight.
        let weight = -Math.round(table.find('.draggable').length / 2);
        // Update the source plugin weights.
        table
          .find(`.status-title-${region}`)
          .nextUntil('.status-title')
          .find('select.source-weight')
          .val(
            // Increment the weight before assigning it to prevent using the
            // absolute minimum available weight. This way we always have an
            // unused upper and lower bound, which makes manually setting the
            // weights easier for users who prefer to do it that way.
            () => {
              weight += 1;
              return weight;
            },
          );
      }

      const table = $('#project_browser');
      // Get the tableDrag object.
      const tableDrag = Drupal.tableDrag.project_browser;
      // Add a handler for when a row is swapped.
      tableDrag.row.prototype.onSwap = function swapHandler() {
        updateLastPlaced(table, this);
      };

      // Add a handler so when a row is dropped, update fields dropped into
      // new regions.
      tableDrag.onDrop = function dropHandler() {
        const dragObject = this;
        const $rowElement = $(dragObject.rowObject.element);
        const regionRow = $rowElement.prevAll('tr.status-title').get(0);
        const regionName = regionRow.classList[1].replace('status-title-', '');
        const regionField = $rowElement.find('select.source-status-select');
        // Update region and weight fields if the region has been changed.
        if (!regionField.is(`.source-status-${regionName}`)) {
          const weightField = $rowElement.find('select.source-weight');
          const oldRegionName = weightField[0].className.replace(
            /([^ ]+[ ]+)*source-weight-([^ ]+)([ ]+[^ ]+)*/,
            '$2',
          );
          regionField
            .removeClass(`source-status-${oldRegionName}`)
            .addClass(`source-status-${regionName}`);
          weightField
            .removeClass(`source-weight-${oldRegionName}`)
            .addClass(`source-weight-${regionName}`);
          regionField.val(regionName);
        }

        updateSourcePluginWeights(table, regionName);
      };

      // Add the behavior to each region select list.
      $(
        once('source-status-select', 'select.source-status-select', context),
      ).on('change', function addBehaviorOnce() {
        // Make our new row and select field.
        const row = $(this).closest('tr');
        const select = $(this);
        // Find the correct region and insert the row as the last in the region.
        // eslint-disable-next-line new-cap
        tableDrag.rowObject = new tableDrag.row(row[0]);
        const regionMessage = table.find(`.status-title-${select[0].value}`);
        const regionItems = regionMessage.nextUntil('.status-title');
        if (regionItems.length) {
          regionItems.last().after(row);
        }
        // We found that regionMessage is the last row.
        else {
          regionMessage.after(row);
        }
        updateSourcePluginWeights(table, select[0].value);
        // Update last placed source plugin indication.
        updateLastPlaced(table, row);
        // Show unsaved changes warning.
        if (!tableDrag.changed) {
          $(Drupal.theme('tableDragChangedWarning'))
            .insertBefore(tableDrag.table)
            .hide()
            .fadeIn('slow');
          tableDrag.changed = true;
        }
        // Remove focus from selectbox.
        select.trigger('blur');
      });
    },
  };
})(jQuery, window, Drupal, once);
