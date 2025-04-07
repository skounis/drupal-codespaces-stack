<script>
  import { createEventDispatcher } from 'svelte';

  const dispatch = createEventDispatcher();
  const { Drupal } = window;

  export let itemTypes = [];
  export let linkTypes = [];
  export let label = '';
  export let toPage = 0;
  export let ariaLabel = null;
  export let isCurrent = false;

  function onChange(event, selectedPage) {
    // Preventing the default behavior which causes the
    // focus to shift on sort-by dropdown element.
    event.preventDefault();
    dispatch('pageChange', {
      page: selectedPage,
      pageIndex: 0,
    });
  }
</script>

<li
  class={`pager__item ${itemTypes
    .map((item) => `pager__item--${item}`)
    .join(' ')}`}
  class:pager__item--active={isCurrent}
>
  <a
    href="#pb-sort"
    class={`pager__link ${linkTypes
      .map((item) => `pager__link--${item}`)
      .join(' ')}`}
    class:is-active={isCurrent}
    aria-label={ariaLabel || Drupal.t('@location page', { '@location': label })}
    on:click={(e) => onChange(e, toPage)}
    aria-current={isCurrent ? 'page' : null}
  >
    {label}
  </a>
</li>
