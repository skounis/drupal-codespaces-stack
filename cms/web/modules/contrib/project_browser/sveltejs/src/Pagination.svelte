<script>
  import { createEventDispatcher, getContext } from 'svelte';
  import PagerItem from './PagerItem.svelte';

  const dispatch = createEventDispatcher();

  function pageSizeChange() {
    dispatch('pageSizeChange');
  }

  const { Drupal } = window;
  const pageSize = getContext('pageSize');

  export let buttons = [-4, -3, -2, -1, 0, 1, 2, 3, 4];
  export let count;
  export let page = 0;
  export let options;

  export let labels = {
    first: Drupal.t('First'),
    last: Drupal.t('Last'),
    next: Drupal.t('Next'),
    previous: Drupal.t('Previous'),
  };

  $: pageCount = Math.ceil(count / $pageSize) - 1;
</script>

<!-- svelte-ignore a11y-no-redundant-roles -->
{#if pageCount > 0}
  <nav
    class="pager pagination__pager"
    aria-label={Drupal.t('Project Browser Pagination')}
    role="navigation"
  >
    {#if options.length > 1}
      <label for="num-projects">
        {Drupal.t('Items per page')}
      </label>
      <select
        class="pagination__num-projects"
        id="num-projects"
        name="num-projects"
        bind:value={$pageSize}
        on:change={() => {
          pageSizeChange();
        }}
      >
        {#each options as option}
          <option value={option}>{option}</option>
        {/each}
      </select>
    {/if}

    <ul class="pagination__pager-items pager__items js-pager__items">
      {#if page !== 0}
        <PagerItem
          on:pageChange
          itemTypes={['action', 'first']}
          linkTypes={['action-link', 'backward']}
          label={labels.first}
          toPage={0}
        />
        <PagerItem
          on:pageChange
          itemTypes={['action', 'previous']}
          linkTypes={['action-link', 'backward']}
          label={labels.previous}
          toPage={page - 1}
        />
      {/if}
      {#if page >= 5}
        <li class="pager__item pager__item--ellipsis" role="presentation">
          &hellip;
        </li>
      {/if}
      {#each buttons as button}
        {#if page + button >= 0 && page + button <= pageCount}
          <PagerItem
            on:pageChange
            itemTypes={['number']}
            isCurrent={button === 0 ? 'page' : null}
            label={page + button + 1}
            toPage={page + button}
            ariaLabel={Drupal.t('Page @page_number', {
              '@page_number': page + button + 1,
            })}
          />
        {/if}
      {/each}
      {#if page + 5 <= pageCount}
        <li class="pager__item pager__item--ellipsis" role="presentation">
          &hellip;
        </li>
      {/if}
      {#if page !== pageCount}
        <PagerItem
          on:pageChange
          itemTypes={['action', 'next']}
          linkTypes={['action-link', 'forward']}
          label={labels.next}
          toPage={page + 1}
        />
        <PagerItem
          on:pageChange
          itemTypes={['action', 'last']}
          linkTypes={['action-link', 'forward']}
          label={labels.last}
          toPage={pageCount}
        />
      {/if}
    </ul>
  </nav>
{/if}
