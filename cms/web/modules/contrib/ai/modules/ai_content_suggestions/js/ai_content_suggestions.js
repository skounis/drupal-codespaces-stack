(function (Drupal, $) {
  'use strict';

  Drupal.behaviors.deepChatToggle = {
    attach: function (context, settings) {
      const selectors = context.querySelectorAll('.toggle_content_suggestion_select');
      selectors.forEach((container) => {
        $(container).hide();
      });

      const toggle_links = context.querySelectorAll('.toggle_content_suggestion_fields');
      toggle_links.forEach((link)=>{
        const $link = $(link);
        const cleanup = function(event){
          $(event.target).parents('.form-item').find('.toggle_content_suggestion_select').toggle();
        };
        $link.off('click', cleanup).on('click', cleanup);
      })

    }
  };

})(Drupal, jQuery);
