(function ($, Drupal, drupalSettings) {

  $(document).ready(function() {
    $('.textarea-loader').hide();
    $('.suggest-alt-text').on('click', function(e) {
      $('input[type=submit]').attr('disabled', true);
      e.preventDefault();
      $('.alt-text-item').each(function() {
        // Make sure they run sequentially.
        getSuggestion(this);
      });
    });

    $('.alt-text-item').on('click', function(e) {
      $('input[type=submit]').attr('disabled', true);
      e.preventDefault();
      getSuggestion(this);
    });
  });

  function getSuggestion(data) {
    let unique = $(data).data('unique-id');
    $('.load-' + unique).show();
    $.ajax({
      url: drupalSettings.path.baseUrl + 'admin/config/ai/ai_image_alt_text/generate/' + $(data).data('file-id') + '/' + $(data).data('entity-language'),
      type: 'GET',
      success: function (data) {
        $('.alt-text-' + unique).val(data.alt_text);
        $('.load-' + unique).hide();
        $('input[type=submit]').attr('disabled', false);
      },
      error: function () {
        if ('error' in response.responseJSON) {
          alert('Error: ' + response.responseJSON.error);
        }
        else {
          alert(Drupal.t('A general error occurred, please try again later.'));
        }
        $('.load-' + unique).hide();
        $('input[type=submit]').attr('disabled', false);
      }
    })
  }

})(jQuery, Drupal, drupalSettings);
