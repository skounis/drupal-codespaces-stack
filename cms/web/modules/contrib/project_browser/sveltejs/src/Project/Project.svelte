<script>
  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let project;
  export let toggleView;
  import { getContext } from 'svelte';
  import ProjectButtonBase from './ProjectButtonBase.svelte';
  import { openPopup } from '../popup';
  import DetailModal from '../DetailModal.svelte';
  import ActionButton from './ActionButton.svelte';
  import Image from './Image.svelte';
  import Categories from './Categories.svelte';
  import ProjectIcon from './ProjectIcon.svelte';
  import { numberFormatter } from '../util';

  const { Drupal } = window;
  const focusedElement = getContext('focusedElement');
  const mediaQueryValues = getContext('mediaQueryValues');

  let mqMatches;
  $: isDesktop = mqMatches;
  $: displayMode = isDesktop ? toggleView.toLowerCase() : 'list';
  mediaQueryValues.subscribe((mqlMap) => {
    mqMatches = mqlMap.get('(min-width: 1200px)');
  });
</script>

<!-- The data-project-id attribute allows tests to target this project precisely. -->
<li class="pb-project pb-project--{displayMode}" data-project-id={project.id}>
  <div class="pb-project__logo pb-project__logo--{displayMode}">
    <Image sources={project.logo} class="pb-project__logo-image" />
  </div>
  <div class="pb-project__main pb-project__main--{displayMode}">
    <h3
      on:click={() => {
        $focusedElement = `${project.project_machine_name}_title`;
      }}
      class="pb-project__title pb-project__title--{displayMode}"
    >
      <ProjectButtonBase
        id="{project.project_machine_name}_title"
        class="pb-project__link"
        aria-haspopup="dialog"
        click={() => {
          const modalDialog = document.createElement('div');
          (() =>
            new DetailModal({
              target: modalDialog,
              props: { project },
            }))();
          openPopup(modalDialog, project);
        }}
      >
        {project.title}
      </ProjectButtonBase>
    </h3>
    <div class="pb-project__body pb-project__body--{displayMode}">
      {@html project.body.summary}
    </div>
    <Categories {toggleView} moduleCategories={project.module_categories} />
  </div>
  <div class="pb-project__icons pb-project__icons--{displayMode}">
    {#if project.is_covered}
      <span class="pb-project__status-icon">
        <ProjectIcon type="status" />
      </span>
    {/if}
    {#if project.is_maintained}
      <span class="pb-project__maintenance-icon">
        <ProjectIcon type="maintained" />
      </span>
    {/if}
    {#if toggleView === 'Grid' && typeof project.project_usage_total === 'number' && project.project_usage_total > 0}
      <div class="pb-project__install-count-container">
        <span class="pb-project__install-count">
          {Drupal.formatPlural(
            project.project_usage_total,
            `${numberFormatter.format(1)} install`,
            `${numberFormatter.format(project.project_usage_total)} installs`,
          )}
        </span>
      </div>
    {/if}
    {#if toggleView === 'List' && typeof project.project_usage_total === 'number' && project.project_usage_total > 0}
      <div class="pb-project__project-usage-container">
        <div class="pb-project__image pb-project__image--{displayMode}">
          <ProjectIcon type="usage" variant="project-listing" />
        </div>
        <div class="pb-project__active-installs-text">
          {Drupal.formatPlural(
            project.project_usage_total,
            `${numberFormatter.format(1)} Active Install`,
            `${numberFormatter.format(
              project.project_usage_total,
            )} Active Installs`,
          )}
        </div>
      </div>
    {/if}
    <ActionButton {project} />
  </div>
</li>
