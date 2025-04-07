/**
 * @file
 * Defines jQuery to provide summary information inside vertical tabs.
 */

(function ($) {
  /**
   * Provide summary information for vertical tabs.
   */
  Drupal.behaviors.scheduler_settings = {
    attach(context) {
      // Provide summary when editing an entity. This is only applicable to
      // themes that provide vertical tabs or modal details blocks with a
      // summary area, such as Bartik or Claro. It does nothing in Stark.
      $('details#edit-scheduler-settings', context).drupalSetSummary(
        function (context) {
          const publishOn = document.querySelector(
            '#edit-publish-on-0-value-date',
          );
          const unpublishOn = document.querySelector(
            '#edit-unpublish-on-0-value-date',
          );
          const values = [];
          if (publishOn?.value) {
            values.push(Drupal.t('Scheduled for publishing'));
          }
          if (unpublishOn?.value) {
            values.push(Drupal.t('Scheduled for unpublishing'));
          }
          if (!values.length) {
            values.push(Drupal.t('Not scheduled'));
          }
          return values.join('<br/>');
        },
      );

      // Provide summary during entity type configuration.
      $('#edit-scheduler', context).drupalSetSummary(function (context) {
        const publishingEnabled = document.querySelector(
          '#edit-scheduler-publish-enable',
        );
        const unpublishingEnabled = document.querySelector(
          '#edit-scheduler-unpublish-enable',
        );
        const values = [];
        if (publishingEnabled.matches(':checked')) {
          values.push(Drupal.t('Publishing enabled'));
        }
        if (unpublishingEnabled.matches(':checked')) {
          values.push(Drupal.t('Unpublishing enabled'));
        }
        return values.join('<br/>');
      });
    },
  };
})(jQuery);
