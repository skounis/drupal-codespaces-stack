(function (Drupal) {
  // Load on document ready, vanilla js.
  document.addEventListener('DOMContentLoaded', function () {
    // Listener to the checkbox on id edit-moderation-checkbox.
    document.getElementById('edit-moderation-checkbox')
      .addEventListener('change', function() {
      // If it is checked, set the button to disabled for 10 seconds.
      if (this.checked) {
        let timer = 10000;
        document.getElementById('edit-submit').disabled = true;
        // Change the value of the button to "Verifying...".
        document.getElementById('edit-submit').value = Drupal.t('Please read the text of the checkbox and wait... 10 seconds');
        let newInterval = setInterval(function() {
          timer -= 1000;
          // Change the value of the button to "Verifying...".
          document.getElementById('edit-submit').value = Drupal.t('Please read the text of the checkbox and wait... ' + (timer/1000) + ' seconds');

          if (timer <= 0) {
            // Clear the interval and enable the button.
            clearInterval(newInterval);
            document.getElementById('edit-submit').disabled = false;
            document.getElementById('edit-submit').value = Drupal.t('Save Configuration');
          }
        }, 1000);

      }
    });
  });
})(Drupal);
