/**
 * @file
 * Contains sam_simplify.js.
 */
(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.SamSimplifyBehavior = {
    attach: function (context, drupalSettings) {
      let bt_text = drupalSettings.sam.add_more_label_var;
      let rm_text = drupalSettings.sam.remove_label_var;
      let helper_text = drupalSettings.sam.help_text_var;
      let helper_text_plural = drupalSettings.sam.help_text_plural_var;

      // For each row, we add a remove button to clear it.
      $(once('sam.simplify.remove-table', '.sam-wrapper-simplify', context)).each(function () {
        const $this = $(this);
        const $container = $this.parentsUntil('.sam-wrapper-simplify');
        // We need to adjust the colspan as we have an extra tr in each row.
        const $thFieldLabel = $('thead tr th.field-label', $container.parent());
        $thFieldLabel.attr('colspan', parseInt($thFieldLabel.attr('colspan')) + 1);
      });

      $(once('sam.simplify.remove-row', '.sam-wrapper-simplify tbody tr', context)).each(function () {
        const $this = $(this);
        if (this.rowIndex === 1) return;

        const button_html = `<td><input type="submit" class="button form-submit sam-remove-button" value="${rm_text}"></td>`;
        $('td.delta-order', $this).before(`${button_html}`);
        $('.sam-remove-button', $this).on('click', function (event) {
          const $this = $(this);
          const $tr = $this.parentsUntil('tr').parent();
          const $container = $this.parentsUntil('.sam-wrapper-simplify').parent();

          // Don't submit the node form.
          event.preventDefault();
          event.stopPropagation();

          // Clear the fields from the widget.
          $('input[type="text"],input[type="hidden"],select,textarea', $tr).each(function() {
            this.value = '';
            var event = new Event('change');
            // We need to manually dispatch the event, e.g. if we have a module
            // like maxlength that needs to listen to the update.
            this.dispatchEvent(event);
          })
          // We need to clear CKEditor editors too!
          $('textarea.text-full', $tr).each(function() {
            // Determine the if we are using CKEditor 4 or 5.
            if (this.dataset && this.dataset.ckeditor5Id) {
              const ckeditor5Id = this.dataset.ckeditor5Id;
              Drupal.CKEditor5Instances.get(ckeditor5Id).setData('', function() { this.updateElement(); } );
            } else if (CKEDITOR && CKEDITOR.instances[this.getAttribute('data-drupal-selector')]) {
              CKEDITOR.instances[this.getAttribute('data-drupal-selector')].setData('', function() { this.updateElement(); } );
            }
          });

          // Move to be the last item, so the widget can be re-used.
          $tr.parent().append($tr);

          // We need to update the weights so they are consistent with visual
          // order.
          // Ideally we would be doing something like this, but tabledrag API is limited.
          // Drupal.tableDrag[$container.attr('id')].updateWeights();
          // Get the lowest weight and update all of them in the order they are
          // visible.
          let weight = $container.find('td.delta-order select').first().children().first().val();
          $container.find('td.delta-order select').each(function() {
            $(this).val(weight);
            weight++;
          });

          // We add the class manually and hide it, so it's used for the count.
          $tr.addClass('sam-simplify');
          $tr.hide();

          // Update the help text.
          let $rows = $container.find('tr.sam-simplify');
          if ($rows.length > 0) {
            const help_text = Drupal.formatPlural($rows.length,  `${helper_text}`, `${helper_text_plural}`);
            const sam_help = $('.sam-add-more-help', $container.parent());
            sam_help.html(help_text);
            sam_help.show();
            $('.sam-add-more-button', $container.parent()).show();
          }
        });
      });

      // See https://www.drupal.org/node/3158256 for the syntax of once().
      $(once('sam.simplify', '.sam-wrapper-simplify', context)).each(function () {
        // If there is nothing to simplify on the page, just bail out.
        const $all_elements_to_simplify = $('tr.sam-simplify', this);

        // Hide all rows that were marked as such.
        // Use fadeOut/In instead of hide/show to avoid the table width collapsing when
        // hiding and showing the last row.
        $all_elements_to_simplify.fadeOut();

        // Append the button(s) that will be used to add other elements.
        const button_html = `<input type="submit" class="button form-submit sam-add-more-button" value="${bt_text}">`;
        const $wrapper = $(this);
        // Add the button with some help text indicating how many spots are
        // left.
        let help_text = Drupal.formatPlural($wrapper.find('tr.sam-simplify').length, `${helper_text}`,  `${helper_text_plural}`);
        $wrapper.parent().append(`${button_html}<span class="sam-add-more-help">${help_text}</span>`);

        if ($all_elements_to_simplify.length === 0) {
          $('.sam-add-more-help', $wrapper.parent()).hide();
          $('.sam-add-more-button', $wrapper.parent()).hide();
        }

        // Attach the click behavior to the buttons.
        $('.sam-add-more-button', $wrapper.parent()).on('click', function (e) {
          const $this = $(this);
          const $wrapper = $this.parent().find('.sam-wrapper-simplify');
          let $rows = $wrapper.find('tr.sam-simplify');

          // Don't submit the node form.
          e.preventDefault();
          e.stopPropagation();

          // Show one additional empty element for each click.
          $rows.first().removeClass('sam-simplify').fadeIn();

          // Update the help text.
          $rows = $wrapper.find('tr.sam-simplify');
          if ($rows.length > 0) {
            const help_text = Drupal.formatPlural($rows.length,  `${helper_text}`, `${helper_text_plural}`);
            $this.next('.sam-add-more-help').html(help_text);
          }
          // If this was the last one, hide the button and the help text.
          else if ($rows.length === 0) {
            $this.next('.sam-add-more-help').hide();
            $this.hide();
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
