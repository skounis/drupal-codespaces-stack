(function (Drupal, once) {
  once('addtocalDialog', 'dialog.add-to-calendar').forEach(function (element) {
    const showButton = element.nextElementSibling;
    const closeButton = element.querySelector('button.close');
    showButton.addEventListener('click', () => {
      element.showModal();
    });
    closeButton.addEventListener('click', () => {
      element.close();
    });
  });
})(Drupal, once);
