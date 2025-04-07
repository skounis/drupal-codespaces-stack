<script>
  import { PACKAGE_MANAGER, MAX_SELECTIONS } from '../constants';
  import { openPopup, getCommandsPopupMessage } from '../popup';
  import ProjectStatusIndicator from './ProjectStatusIndicator.svelte';
  import LoadingEllipsis from './LoadingEllipsis.svelte';
  import DropButton from './DropButton.svelte';
  import ProjectButtonBase from './ProjectButtonBase.svelte';
  import {
    processInstallList,
    addToInstallList,
    installList,
    removeFromInstallList,
  } from '../InstallListProcessor';
  import ProjectIcon from './ProjectIcon.svelte';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let project;
  let InstallListFull;

  const { Drupal } = window;
  const processMultipleProjects = MAX_SELECTIONS === null || MAX_SELECTIONS > 1;

  $: isInInstallList = $installList.some((item) => item.id === project.id);

  // If MAX_SELECTIONS is null (no limit), then the install list is never full.
  $: InstallListFull = $installList.length === MAX_SELECTIONS;

  function handleAddToInstallListClick(singleProject) {
    addToInstallList(singleProject);
  }

  function handleRemoveFromInstallList(projectId) {
    removeFromInstallList(projectId);
  }

  const onClick = async () => {
    if (processMultipleProjects) {
      if (isInInstallList) {
        handleRemoveFromInstallList(project.id);
      } else {
        handleAddToInstallListClick(project);
      }
    } else {
      handleAddToInstallListClick(project);
      await processInstallList();
    }
  };
</script>

<div class="pb-actions">
  {#if !project.is_compatible}
    <ProjectStatusIndicator {project} statusText={Drupal.t('Not compatible')} />
  {:else if project.status === 'active'}
    <ProjectStatusIndicator {project} statusText={Drupal.t('Installed')}>
      <ProjectIcon type="installed" />
    </ProjectStatusIndicator>
    {#if project.tasks.length > 0}
      <DropButton tasks={project.tasks} />
    {/if}
  {:else}
    <span>
      {#if PACKAGE_MANAGER}
        {#if isInInstallList && !processMultipleProjects}
          <ProjectButtonBase>
            <LoadingEllipsis />
          </ProjectButtonBase>
        {:else if InstallListFull && !isInInstallList && processMultipleProjects}
          <ProjectButtonBase disabled>
            {@html Drupal.t(
              'Select <span class="visually-hidden">@title</span>',
              {
                '@title': project.title,
              },
            )}
          </ProjectButtonBase>
        {:else}
          <ProjectButtonBase click={onClick}>
            {#if isInInstallList}
              {@html Drupal.t(
                'Deselect <span class="visually-hidden">@title</span>',
                {
                  '@title': project.title,
                },
              )}
            {:else if processMultipleProjects}
              {@html Drupal.t(
                'Select <span class="visually-hidden">@title</span>',
                {
                  '@title': project.title,
                },
              )}
            {:else}
              {@html Drupal.t(
                'Install <span class="visually-hidden">@title</span>',
                {
                  '@title': project.title,
                },
              )}
            {/if}
          </ProjectButtonBase>
        {/if}
      {:else if project.commands}
        {#if project.commands.match(/^https?:\/\//)}
          <a href={project.commands} target="_blank" rel="noreferrer"
            ><ProjectButtonBase>{Drupal.t('Install')}</ProjectButtonBase></a
          >
        {:else}
          <ProjectButtonBase
            aria-haspopup="dialog"
            click={() => openPopup(getCommandsPopupMessage(project), project)}
          >
            {@html Drupal.t(
              'View Commands <span class="visually-hidden">for @title</span>',
              {
                '@title': project.title,
              },
            )}
          </ProjectButtonBase>
        {/if}
      {/if}
    </span>
  {/if}
</div>
