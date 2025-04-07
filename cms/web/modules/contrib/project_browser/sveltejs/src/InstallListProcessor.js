import { get, writable } from 'svelte/store';
import { openPopup } from './popup';
import { BASE_URL, CURRENT_PATH } from './constants';

const { Drupal } = window;

// Store for the install list.
export const installList = writable([]);

export function addToInstallList(project) {
  installList.update((currentList) => {
    if (!currentList.includes(project)) {
      currentList.push(project);
    }
    return currentList;
  });
}

export function removeFromInstallList(projectId) {
  installList.update((currentList) => currentList.filter(
      (item) => item.id !== projectId,
    ));
}

export function clearInstallList() {
  installList.set([]);
}

export const handleError = async (errorResponse) => {
  // The error can take on many shapes, so it should be normalized.
  let err = '';
  if (typeof errorResponse === 'string') {
    err = errorResponse;
  } else {
    err = await errorResponse.text();
  }
  try {
    // See if the error string can be parsed as JSON. If not, the block
    // is exited before the `err` string is overwritten.
    const parsed = JSON.parse(err);
    err = parsed;
  } catch {
    // The catch behavior is established before the try block.
  }

  const errorMessage = err.message || err;

  // The popup function expects an element, so a div containing the error
  // message is created here for it to display in a modal.
  const div = document.createElement('div');

  const currentUrl =
    window.location.pathname + window.location.search + window.location.hash;

  if (err.unlock_url) {
    try {
      const unlockUrl = new URL(err.unlock_url, BASE_URL);
      unlockUrl.searchParams.set('destination', currentUrl);

      const updatedMessage = errorMessage.replace(
        '[+ unlock link]',
        `<a href="${
          unlockUrl.pathname + unlockUrl.search
        }" id="unlock-link">${Drupal.t('unlock link')}</a>`,
      );

      div.innerHTML += `<p>${updatedMessage}</p>`;
    } catch {
      div.innerHTML += `<p>${errorMessage}</p>`;
    }
  } else {
    div.innerHTML += `<p>${errorMessage}</p>`;
  }

  openPopup(div, { title: 'Error while installing package(s)' });
};

/**
 * Actives already-downloaded projects.
 *
 * @param {string[]} projectIds
 *   An array of project IDs to activate.
 *
 * @return {Promise<void>}
 *   A promise that resolves when the project is activated.
 */
export const activateProject = async (projectIds) => {
  // Remove any existing errors for each project individually.
  const messenger = new Drupal.Message();
  projectIds.forEach((projectId) => {
    const messageId = `activation_error:${projectId}`;
    if (messenger.select(messageId)) {
      messenger.remove(messageId);
    }
  });

  await new Drupal.Ajax(
    null,
    document.createElement('div'),
    {
      url: `${BASE_URL}admin/modules/project_browser/activate?projects=${projectIds.join(',')}`,
    },
  ).execute();
};

/**
 * Performs the requests necessary to download and activate project via Package Manager.
 *
 * @param {string[]} projectIds
 *   An array of project IDs to download and activate.
 *
 * @return {Promise<void>}
 *   Returns a promise that resolves once the download and activation process is complete.
 */
export const doRequests = async (projectIds) => {
  const beginInstallUrl = `${BASE_URL}admin/modules/project_browser/install-begin?redirect=${
    CURRENT_PATH
  }`;
  const beginInstallResponse = await fetch(beginInstallUrl);
  if (!beginInstallResponse.ok) {
    await handleError(beginInstallResponse);
  } else {
    const beginInstallData = await beginInstallResponse.json();
    const stageId = beginInstallData.stage_id;

    // The process of adding a module is separated into four stages, each
    // with their own endpoint. When one stage completes, the next one is
    // requested.
    const installSteps = [
      {
        url: `${BASE_URL}admin/modules/project_browser/install-require/${stageId}`,
        method: 'POST',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-apply/${stageId}`,
        method: 'GET',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-post_apply/${stageId}`,
        method: 'GET',
      },
      {
        url: `${BASE_URL}admin/modules/project_browser/install-destroy/${stageId}`,
        method: 'GET',
      },
    ];

    // eslint-disable-next-line no-restricted-syntax,guard-for-in
    for (const step of installSteps) {
      const options = {
        method: step.method,
      };

      // Additional options need to be added when the request method is POST.
      // This is specifically required for the `install-require` step.
      if (step.method === 'POST') {
        options.headers = {
          'Content-Type': 'application/json',
        };

        // Set the request body to include the project(s) id as an array.
        options.body = JSON.stringify(projectIds);
      }
      // eslint-disable-next-line no-await-in-loop
      const stepResponse = await fetch(step.url, options);
      if (!stepResponse.ok) {
        // eslint-disable-next-line no-await-in-loop
        const errorMessage = await stepResponse.text();
        // eslint-disable-next-line no-console
        console.warn(
          `failed request to ${step.url}: ${errorMessage}`,
          stepResponse,
        );
        // eslint-disable-next-line no-await-in-loop
        await handleError(errorMessage);
        return;
      }
    }
    await activateProject(projectIds);
  }
};

export const processInstallList = async () => {
  const currentInstallList = get(installList) || [];
  const projectsToActivate = [];
  const projectsToDownloadAndActivate = [];
  if (currentInstallList.length === 0) {
    const messageElement = document.querySelector('[data-drupal-message-id="install_message"]');

    if (!messageElement) {
      // If the message does not exist, create a new one.
      new Drupal.Message().add(Drupal.t('No projects selected'), { type: 'error', id: 'install_message' });
    } else if (messageElement.classList.contains('visually-hidden')) {
      // If the message exists but is visually hidden, remove the class and reset opacity.
      messageElement.classList.remove('visually-hidden');
      messageElement.style.opacity = 1;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  for (const proj of currentInstallList) {
    if (proj.status === 'absent') {
      projectsToDownloadAndActivate.push(proj.id);
    } else if (proj.status === 'present') {
      projectsToActivate.push(proj.id);
    }
  }

  document.body.style.pointerEvents = 'none';

  if (projectsToActivate.length > 0) {
    await activateProject(projectsToActivate);
  }
  if (projectsToDownloadAndActivate.length > 0) {
    await doRequests(projectsToDownloadAndActivate);
  }

  document.body.style.pointerEvents = 'auto';

  clearInstallList();
};
