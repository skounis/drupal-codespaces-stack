<script>
  import ProjectBrowser from './ProjectBrowser.svelte';

  // eslint-disable-next-line import/prefer-default-export
  export let id;

  // Removes initial loader if it exists.
  const initialLoader = document.getElementById('initial-loader');
  if (initialLoader) {
    initialLoader.remove();
  }
</script>

<ProjectBrowser {id} />
