<script>
  import { createEventDispatcher, getContext } from 'svelte';

  const { Drupal } = window;
  const dispatch = createEventDispatcher();
  const filters = getContext('filters');

  // eslint-disable-next-line import/prefer-default-export
  export let choices;
  export let name;
  export let filterList;

  let filterVisible = false;
  let lastFocusedCheckbox = null;

  // Internal references to the elements that comprise this component.
  let dropdownElement;
  let dropdownItemsElement;

  const componentId = `pb-filter-${Math.random().toString(36).substring(2, 9)}`;

  function showHideFilter() {
    filterVisible = !filterVisible;
    if (filterVisible) {
      dropdownItemsElement.classList.add(
        'pb-filter__multi-dropdown__items--visible',
      );
    } else {
      dropdownItemsElement.classList.remove(
        'pb-filter__multi-dropdown__items--visible',
      );
    }
    setTimeout(() => {
      // Ensure focus stays on the last focused checkbox
      if (lastFocusedCheckbox) {
        lastFocusedCheckbox.focus();
      } else if (dropdownElement) {
        const firstCheckbox = dropdownElement.querySelector(
          '.pb-filter__checkbox',
        );
        if (firstCheckbox) firstCheckbox.focus();
      }
    }, 50);
  }

  function onBlur(event) {
    if (
      event.relatedTarget === null ||
      !dropdownElement.contains(event.relatedTarget)
    ) {
      filterVisible = false;
      dropdownItemsElement.classList.remove(
        'pb-filter__multi-dropdown__items--visible',
      );
    }
  }

  function onKeyDown(event) {
    const isCheckbox = event.target.matches('input');
    const checkboxes = dropdownItemsElement.querySelectorAll('input');
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      return;
    }
    if (event.key === ' ' && event.target === dropdownElement) {
      showHideFilter();
      event.preventDefault();
      return;
    }
    // Alt Up/Down opens/closes category filter drop-down.
    if (
      event.altKey &&
      (event.key === 'ArrowDown' || event.key === 'ArrowUp')
    ) {
      showHideFilter();
      event.preventDefault();
      return;
    }
    // Prevent tabbing out when the filter is expanded.
    if (event.key === 'Tab' && filterVisible) {
      event.preventDefault();
      return;
    }
    // Down arrow on checkbox moves to next checkbox or wraps around.
    if (
      dropdownItemsElement.contains(event.target) &&
      event.key === 'ArrowDown'
    ) {
      const nextElement =
        event.target.parentElement.parentElement.nextElementSibling;
      if (nextElement) {
        nextElement.firstElementChild.focus();
      } else {
        // Wrap to the first item
        checkboxes[0].focus();
      }
      event.preventDefault();
      return;
    }

    // Up arrow on checkbox moves to previous checkbox or wraps around.
    if (isCheckbox && event.key === 'ArrowUp') {
      const prevElement =
        event.target.parentElement.parentElement.previousElementSibling;
      if (prevElement) {
        prevElement.firstElementChild.focus();
      } else {
        // Wrap to the last item
        checkboxes[checkboxes.length - 1].focus();
      }
      event.preventDefault();
      return;
    }
    // Prevent dropdown collapse when moving focus with the arrow key.
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
    }
    // Tab moves off filter.
    if (event.key === 'Tab') {
      if (event.shiftKey) {
        // Shift+tab moves to search box.
        document.getElementById('pb-text').focus();
        event.preventDefault();
        return;
      }
      // Tab without shift moves to next filter.
      const indexOfMyself = filterList.indexOf(name);
      if (indexOfMyself !== -1 && indexOfMyself + 1 < filterList.length) {
        const nextElementKey = filterList[indexOfMyself + 1];
        document.getElementsByName(nextElementKey)[0].focus();
        event.preventDefault();
      }
      return;
    }

    // Escape closes filter drop-down.
    if (isCheckbox && event.key === 'Escape') {
      filterVisible = false;
      dropdownElement.focus();
      dropdownItemsElement.classList.remove(
        'pb-filter__multi-dropdown__items--visible',
      );
    }
  }

  async function onChange(event) {
    dispatch('FilterChange');
    filterVisible = true;
    dropdownItemsElement.classList.add(
      'pb-filter__multi-dropdown__items--visible',
    );
    if (event.target.matches('input')) {
      lastFocusedCheckbox = event.target;
      setTimeout(() => {
        lastFocusedCheckbox.focus();
      }, 50);
    }
  }
</script>

<div class="filter-group__filter-options form-item">
  <label
    for={`${componentId}-dropdown`}
    class="form-item__label"
    on:click={(event) => {
      event.preventDefault();
      dropdownElement.focus();
      if (!filterVisible) {
        showHideFilter();
      }
    }}>{Drupal.t('Filter by category')}</label
  >
  <div
    id={`${componentId}-dropdown`}
    role="group"
    tabindex="0"
    class="pb-filter__multi-dropdown form-element form-element--type-select"
    on:click={() => {
      showHideFilter();
    }}
    on:blur={onBlur}
    on:keydown={onKeyDown}
    bind:this={dropdownElement}
  >
    <span class="pb-filter__multi-dropdown__label">
      {#if $filters[name].length > 0}
        {@html Drupal.formatPlural(
          $filters[name].length,
          '1 category selected',
          '@count categories selected',
        )}
      {:else}
        {Drupal.t('Select categories')}
      {/if}
    </span>
    <div
      class="pb-filter__multi-dropdown__items
      pb-filter__multi-dropdown__items--{filterVisible ? 'visible' : 'hidden'}"
      bind:this={dropdownItemsElement}
    >
      {#each Object.entries(choices) as [id, label]}
        <div class="pb-filter__checkbox__container">
          <label>
            <input
              type="checkbox"
              class="pb-filter__checkbox form-checkbox form-boolean form-boolean--type-checkbox"
              bind:group={$filters[name]}
              on:change={onChange}
              on:blur={onBlur}
              on:keydown={onKeyDown}
              value={id}
            />
            {label}
          </label>
        </div>
      {/each}
    </div>
  </div>
</div>
