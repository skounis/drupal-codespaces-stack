/**
 * @file
 * Attaches show/hide functionality to checkboxes in the "Augmenter" form.
 */

(function (Drupal) {

  'use strict';

  Drupal.behaviors.dateAugmenter = {
    attach: function (context, settings) {
      document.querySelectorAll('.date-augmenter-status-wrapper input.form-checkbox', context).forEach(function (element) {
        element.addEventListener("click", e => {
          toggleCheckBox(element);
        });

        // Trigger our helper function to update elements to initial state.
        toggleCheckBox(element);
      });

      // Helper function to toggle the visibility of dependent elements.
      function toggleCheckBox(element) {
        const processor_id = element.dataset.id;
        const $wrapper = element.closest('.date-augmenter-status-wrapper').parentElement;
        const $rows = $wrapper.querySelector('.date-augmenter-weight--' + processor_id);
        const $tab = $wrapper.querySelector('.date-augmenter-settings-' + processor_id);

        if (element.checked) {
          $rows.style.display = '';
          if ($tab) {
            $tab.style.display = '';
          }
        }
        else {
          $rows.style.display = 'none';
          if ($tab) {
            $tab.style.display = 'none';
          }
        }
      }
    }
  };

}(Drupal));
