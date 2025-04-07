/**
 * @file
 * Behaviors of friendlycaptcha module to reset the friendly captcha on the
 * given context to also setup on AJAX requests.
 */

 (function () {
  "use strict";

  /**
   * Behavior to initialize friendlycaptcha in the given context, if not
   * yet initialized.
   *
   * @type {{attach: Drupal.behaviors.initFriendlyCaptcha.attach}}
   */
  Drupal.behaviors.initFriendlyCaptcha = {
    attach: function (context) {
      // Only run if friendlyChallenge is initialized on the page:
      if(window.friendlyChallenge){
        // Until https://github.com/FriendlyCaptcha/friendly-challenge/issues/81
        // is solved, we have to completely reinitialize the widgets
        // as we can't access the widgets from DOM currently.
        var friendlyCaptchaDomElements = context.querySelectorAll(".frc-captcha");
        friendlyCaptchaDomElements.forEach(function (friendlyCaptchaDomElement) {
          // Only reset if the widget is not initialized yet.
          // If it is, it has child nodes.
          if(!friendlyCaptchaDomElement.hasChildNodes()){
            var friendlyChallengeWidget = new window.friendlyChallenge.WidgetInstance(friendlyCaptchaDomElement);
            friendlyChallengeWidget.reset();
          }
        });
      }
    }
  };

})();
