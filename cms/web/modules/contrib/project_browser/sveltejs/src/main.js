import App from './App.svelte';

document.querySelectorAll('[data-project-browser-instance-id]').forEach((element) => {
  new App({
    // The #project-browser markup is returned by the project_browser.browse Drupal route.
    target: element,
    props: {
      id: element.getAttribute('data-project-browser-instance-id'),
    },
  });
});
