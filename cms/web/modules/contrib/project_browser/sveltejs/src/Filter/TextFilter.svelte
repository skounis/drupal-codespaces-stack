<script>
  import { createEventDispatcher, getContext } from 'svelte';
  import { DARK_COLOR_SCHEME, FULL_MODULE_PATH } from '../constants';

  const { Drupal } = window;
  const filters = getContext('filters');
  const dispatch = createEventDispatcher();
  export let name;
  export let refresh;
  function onClick() {
    dispatch('FilterChange', {
      filters: $filters,
    });
    refresh();
  }

  function clearText() {
    $filters.search = '';
    onClick();
    document.getElementById('pb-text').focus();
  }
</script>

<div
  class="search__bar-container search__form-item js-form-item form-item js-form-type-textfield form-type--textfield"
  role="search"
>
  <label for="pb-text" class="form-item__label">{Drupal.t('Search')}</label>
  <div class="search__search-bar">
    <input
      class="search__search_term form-text form-element form-element--type-text"
      type="search"
      id="pb-text"
      {name}
      bind:value={$filters[name]}
      on:keydown={(e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          onClick();
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          clearText();
        }
      }}
    />
    {#if $filters[name]}
      <button
        class="search__search-clear"
        id="clear-text"
        type="button"
        on:click={clearText}
        aria-label={Drupal.t('Clear search text')}
        tabindex="-1"
      >
        <img
          src="{FULL_MODULE_PATH}/images/cross{DARK_COLOR_SCHEME
            ? '--dark-color-scheme'
            : ''}.svg"
          alt=""
        />
      </button>
    {/if}
    <button
      class="search__search-submit"
      type="button"
      on:click={onClick}
      aria-label={Drupal.t('Search')}
    >
      <img
        class="search__search-icon"
        id="search-icon"
        src="{FULL_MODULE_PATH}/images/search-icon{DARK_COLOR_SCHEME
          ? '--dark-color-scheme'
          : ''}.svg"
        alt=""
      />
    </button>
  </div>
</div>
