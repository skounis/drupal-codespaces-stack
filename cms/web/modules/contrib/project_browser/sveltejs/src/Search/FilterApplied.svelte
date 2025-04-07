<script>
  import { FULL_MODULE_PATH } from '../constants';

  export let label;
  export let clickHandler;

  const { Drupal } = window;
</script>

<p class="filter-applied">
  <span class="filter-applied__label">{label}</span>
  <button
    type="button"
    on:click={clickHandler}
    class="filter-applied__button-close"
  >
    {#if label}
      <span class="visually-hidden"
        >{Drupal.t('Remove @filter', { '@filter': label })}</span
      >
    {/if}
    <img src="{FULL_MODULE_PATH}/images/chip-close-icon.svg" alt="" />
  </button>
</p>
