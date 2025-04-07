(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.aiAltImage = {
    trackedImages: {},
    finishedWorking: (that) => {
      // Untrack the file.
      Drupal.behaviors.aiAltImage.trackedImages[$(that).data('file-id')] = false;
      // Remove the throbber.
      $(that).parent().find('.ajax-progress').remove();
      // Enable the button.
      if (!drupalSettings.ai_image_alt_text.hide_button) {
        $(that).show();
      }
      // Enable the text field.
      $(that).parent('.form-managed-file').find("input[name$='[alt]']").removeAttr('disabled');
    },
    attach: (context) => {
      $('.ai-alt-text-generation').off('click').on('click', function (e) {
        // Set that it is being tracked.
        Drupal.behaviors.aiAltImage.trackedImages[$(this).data('file-id')] = true;
        e.preventDefault();
        // Manually add the throbber.
        let throbber = $('<div class="ajax-progress ajax-progress--throbber"><div class="ajax-progress__throbber">&nbsp;</div><div class="ajax-progress__message">' + Drupal.t('Generating alt text...') + '</div></div>');
        $(this).parent().append(throbber);
        $(this).parent('.form-managed-file').find("input[name$='[alt]']").attr('disabled', 'disabled');
        // Disable the button.
        $(this).hide();
        let that = $(this);
        let lang = drupalSettings.ai_image_alt_text.lang;
        $.ajax({
          url: drupalSettings.path.baseUrl + 'admin/config/ai/ai_image_alt_text/generate/' + $(this).data('file-id') + '/' + lang,
          type: 'GET',
          success: function (response) {
            if ('alt_text' in response) {
              $(that).parents('.form-managed-file').find("input[name$='[alt]']").val(response.alt_text);
            }
            Drupal.behaviors.aiAltImage.finishedWorking(that);
          },
          error: function (response) {
            let messenger = new Drupal.Message();
            if ('responseJSON' in response && 'error' in response.responseJSON) {
              messenger.add('Error: ' + response.responseJSON.error, { type: 'warning' });
            }
            else {
              messenger.add(Drupal.t('We could not create an Alt Text, please try again later.'), { type: 'warning' });
            }
            Drupal.behaviors.aiAltImage.finishedWorking(that);
          }
        });
      });

      // Check for newly created elements without alt text.
      if (drupalSettings.ai_image_alt_text.autogenerate) {
        $(context).find('.ai-alt-text-generation').each(function () {
          if ($(this).parents('.form-managed-file').find("input[name$='[alt]']").val() === '') {
            // Check so the file id is not already being worked on.
            if (Drupal.behaviors.aiAltImage.trackedImages[$(this).data('file-id')] !== true) {
              $(this).trigger('click');
            }
          }
        });
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
