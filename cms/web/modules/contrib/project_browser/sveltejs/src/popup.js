// cspell:ignore dont
const { once, Drupal, bodyScrollLock } = window;

/**
 * Finds [data-copy-command] buttons and adds copy functionality to them.
 */
const enableCopyButtons = () => {
  setTimeout(() => {
    once('copyButton', '[data-copy-command]').forEach((copyButton) => {
      // If clipboard is not supported (likely due to non-https), then hide the
      // button and do not bother with event listeners
      if (!navigator.clipboard) {
        // copyButton.hidden = true;
        // return;
      }
      copyButton.addEventListener('click', (e) => {
        // The copy button must be contained in a div
        const container = e.target.closest('div');
        // The only <textarea> within the parent div should have its value set
        // to the command that should be copied.
        const input = container.querySelector('textarea');

        // Make the input value the selected text
        input.select()
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        Drupal.announce(Drupal.t('Copied text to clipboard'));

        // Create a "receipt" that will visually show the text has been copied.
        const receipt = document.createElement('div')
        receipt.textContent = Drupal.t('Copied')
        receipt.classList.add('copied-action')
        receipt.style.opacity = '1';
        input.insertAdjacentElement('afterend', receipt)
        // eslint-disable-next-line max-nested-callbacks
        setTimeout(() => {
          // Remove the receipt after 1 second.
          receipt.remove()
        }, 1000);
      })
    })
  })
}

export const getCommandsPopupMessage = (project) => {
  const div = document.createElement('div');
  div.innerHTML = project.commands + '<style>.action-link { margin: 0 2px; padding: 0.25rem 0.25rem; border: 1px solid; }</style>';
  enableCopyButtons();
  return div;
};

export const openPopup = (getMessage, project) => {
  const message = typeof getMessage === 'function' ? getMessage() : getMessage;
  const isModuleDetail = getMessage.firstElementChild.classList.contains('pb-detail-modal');

  const popupModal = Drupal.dialog(message, {
    title: project.title,
    classes: { 'ui-dialog': isModuleDetail ? 'project-browser-detail-modal' : 'project-browser-popup' },
    width: '90vw',
    close: () => {
      document.querySelector('.ui-dialog').remove();
      bodyScrollLock.clearBodyLocks()
    }
  });
  popupModal.showModal();
  const modalElement = document.querySelector('.project-browser-detail-modal');
  if (modalElement) {
    modalElement.focus();
  }
};
