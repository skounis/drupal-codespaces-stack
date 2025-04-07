<script>
  import { getContext } from 'svelte';

  export let definition;
  const filters = getContext('filters');

  export let changeHandler;
  export let name;
</script>

<div class="filter-group__filter-options form-item form-type--checkbox">
  <label>
    <input
      {name}
      class="form-checkbox form-boolean form-boolean--type-checkbox"
      type="checkbox"
      bind:checked={$filters[name]}
      on:change={changeHandler}
    />
    <span class="checkbox-label">{definition.name}</span></label
  >
</div>
