<script>
  import { onMount } from 'svelte';
  import ActionButton from './Project/ActionButton.svelte';
  import Image from './Project/Image.svelte';
  import ImageCarousel from './ImageCarousel.svelte';
  import ProjectIcon from './Project/ProjectIcon.svelte';
  import { numberFormatter } from './util';
  import { PACKAGE_MANAGER } from './constants';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let project;
  const { Drupal } = window;
  onMount(() => {
    const description = document.createElement('div');
    description.innerHTML = project.body.value ? project.body.value : '';
    const anchors = description.getElementsByTagName('a');
    for (let i = 0; i < anchors.length; i++) {
      anchors[i].setAttribute('target', '_blank');
      anchors[i].setAttribute('rel', 'noopener noreferrer');
    }
    project.body.value = description.innerHTML;
  });
</script>

<div class="pb-detail-modal">
  <div class="pb-detail-modal__main">
    <div class="pb-detail-modal__header">
      <Image sources={project.logo} class="pb-detail-modal__title-logo" />
      <div class="pb-detail-modal__title">
        <h2 class="pb-detail-modal__title-label">{project.title}</h2>
      </div>
    </div>
    <div class="pb-detail-modal__details">
      {#if project.module_categories.length}
        <div class="pb-detail-modal__categories">
          <strong>Categories:</strong>
          <span>
            {project.module_categories
              .map((category) => category.name)
              .join(', ')}
          </span>
        </div>
      {/if}
    </div>
    <div class="pb-detail-modal__description" id="summary-wrapper">
      {@html project.body.summary}
    </div>
    {#if project.project_images.length > 0}
      <div class="pb-detail-modal__carousel-wrapper">
        <ImageCarousel sources={project.project_images} />
      </div>
    {/if}
    {#if project.body.value}
      <div class="pb-detail-modal__description" id="description-wrapper">
        {@html project.body.value}
      </div>
    {/if}
  </div>
  <div class="pb-detail-modal__sidebar">
    {#if PACKAGE_MANAGER}
      <div
        class="pb-detail-modal__view-commands pb-detail-modal__sidebar_element"
      >
        <ActionButton {project} />
      </div>
    {/if}
    {#if project.is_compatible}
      <div class="pb-detail-modal__sidebar_element">
        <ProjectIcon
          type="compatible"
          variant="module-details"
          classes="pb-detail-modal__module-details-icon-sidebar"
        />
        {Drupal.t('Compatible with your Drupal installation')}
      </div>
    {/if}
    {#if !project.is_compatible}
      <div class="pb-detail-modal__sidebar_element">
        {Drupal.t('Not compatible with your Drupal installation')}
      </div>
    {/if}
    {#if typeof project.project_usage_total === 'number'}
      <div class="pb-detail-modal__sidebar_element">
        <ProjectIcon
          type="usage"
          variant="module-details"
          classes="pb-detail-modal__module-details-icon-sidebar"
        />
        {Drupal.formatPlural(
          project.project_usage_total,
          `${numberFormatter.format(1)} site reports using this module`,
          `${numberFormatter.format(
            project.project_usage_total,
          )} sites report using this module`,
        )}
      </div>
    {/if}
    {#if project.is_covered}
      <div class="pb-detail-modal__sidebar_element">
        <ProjectIcon
          type="status"
          variant="module-details"
          classes="pb-detail-modal__module-details-icon-sidebar"
        />
        {Drupal.t(
          'Stable releases for this project are covered by the security advisory policy',
        )}
      </div>
    {/if}
    {#if project.is_maintained}
      <div class="pb-module-page__sidebar_element">
        <ProjectIcon
          type="maintained"
          variant="module-details"
          classes="pb-module-page__module-details-icon-sidebar"
        />
        {Drupal.t('The module is actively maintained by the maintainers')}
      </div>
    {/if}

    {#if project.url}
      <div
        class="pb-detail-modal__view-commands pb-detail-modal__sidebar_element"
      >
        <button
          class="project__action_button"
          onclick={`window.open('${project.url}', '_blank', 'noopener,noreferrer')`}
          >{Drupal.t('Learn more')}</button
        >
      </div>
    {/if}
  </div>
</div>
