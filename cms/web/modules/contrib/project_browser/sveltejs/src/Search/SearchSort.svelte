<script>
  import { createEventDispatcher, getContext } from 'svelte';

  const sort = getContext('sort');

  export let sortText;
  export let refresh;
  export let sorts;

  const { Drupal } = window;
  const dispatch = createEventDispatcher();

  async function onSort() {
    dispatch('sort', {
      sort: $sort,
    });
    sortText = sorts[$sort];
    refresh();
  }
</script>

<div class="search__sort">
  <label for="pb-sort" class="form-item__label">{Drupal.t('Sort by')}</label>
  <select
    name="pb-sort"
    id="pb-sort"
    bind:value={$sort}
    on:change={onSort}
    class="search__sort-select form-select form-element form-element--type-select"
  >
    {#each Object.entries(sorts) as [id, text]}
      <option value={id}>
        {text}
      </option>
    {/each}
  </select>
</div>
