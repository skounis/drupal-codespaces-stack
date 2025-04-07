<script>
  import {
    processInstallList,
    installList,
    clearInstallList,
  } from './InstallListProcessor';
  import Loading from './Loading.svelte';
  import LoadingEllipsis from './Project/LoadingEllipsis.svelte';

  let loading = false;

  const { Drupal } = window;

  $: currentInstallList = $installList || [];
  $: installListLength = currentInstallList.length;
  let projectsToActivate = [];
  let projectsToDownloadAndActivate = [];

  const handleClick = async () => {
    loading = true;
    await processInstallList();
    loading = false;
  };

  function clearSelection() {
    projectsToDownloadAndActivate = [];
    projectsToActivate = [];
    clearInstallList();
  }
</script>

<div
  class="views-bulk-actions pb-install_bulk_actions"
  data-drupal-sticky-vbo={installListLength !== 0}
>
  <div
    class="views-bulk-actions__item
  views-bulk-actions__item--status"
  >
    {#if installListLength === 0}
      {Drupal.t('No projects selected')}
    {:else}
      {Drupal.formatPlural(
        installListLength,
        '1 project selected',
        '@count projects selected',
      )}
    {/if}
  </div>
  <button
    class="project__action_button install_button_common install_button button--small button button--primary"
    on:click={handleClick}
  >
    {#if loading}
      <Loading />
      <LoadingEllipsis
        message={Drupal.formatPlural(
          installListLength,
          'Installing 1 project',
          'Installing @count projects',
        )}
      />
    {:else}
      {Drupal.t('Install selected projects')}
    {/if}
  </button>
  {#if installListLength !== 0}
    <button class="button clear_button" on:click={clearSelection}>
      {Drupal.t('Clear selection')}
    </button>
  {/if}
</div>
