<script>
  export let project;
  export let statusText;

  const { Drupal } = window;
</script>

<button class="project_status-indicator" aria-disabled="true">
  <slot />
  <span class="visually-hidden">
    {Drupal.t('@module is', {
      '@module': `${project.title}`,
    })}
  </span>
  <span class="project_status-indicator__label">{statusText}</span>
</button>
