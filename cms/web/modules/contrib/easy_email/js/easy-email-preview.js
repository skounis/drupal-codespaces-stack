(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.resizablePreview = {
    attach: function (context, settings) {
      $(once('resizable-preview', '.easy-email-resizable', context)).each(function(i, element) {

        $(element).resizable({
          minHeight: 100,
          minWidth: 200
        });
      });
    }
  };

})(jQuery, Drupal, once);
