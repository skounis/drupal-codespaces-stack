/**
 * @file
 * Javascript functionality for the Trash overview page.
 */

(function (Drupal) {
  /**
   * Entity type selector on the Trash overview page.
   */
  Drupal.behaviors.trashSelectEntityType = {
    attach(context) {
      once('trash-select-entity-type', '.trash-entity-type', context).forEach(
        (trigger) => {
          trigger.addEventListener('change', (e) => {
            window.location = Drupal.url(
              `admin/content/trash/${e.target.value}`,
            );
          });
        },
      );
    },
  };
})(Drupal);
