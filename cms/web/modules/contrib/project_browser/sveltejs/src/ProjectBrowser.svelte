<script>
  import { onMount, setContext } from 'svelte';
  import { writable } from 'svelte/store';
  import { withPrevious } from 'svelte-previous';
  import ProjectGrid, { Search } from './ProjectGrid.svelte';
  import Pagination from './Pagination.svelte';
  import Project from './Project/Project.svelte';
  import { numberFormatter } from './util';
  import { installList } from './InstallListProcessor';
  import MediaQuery from './MediaQuery.svelte';
  import { FULL_MODULE_PATH, MAX_SELECTIONS } from './constants';
  import QueryManager from './QueryManager';
  import Loading from './Loading.svelte';

  const { Drupal, drupalSettings } = window;
  const { announce } = Drupal;

  // eslint-disable-next-line import/prefer-default-export
  export let id;
  const {
    filters: filterDefinitions,
    source,
    name,
    sorts,
    sortBy,
    paginate,
    pageSizes,
  } = drupalSettings.project_browser.instances[id];

  const queryManager = new QueryManager(paginate);
  const numberOfFilters = Object.keys(filterDefinitions).length;
  const numberOfSorts = Object.keys(sorts).length;

  const filters = writable({});
  Object.entries(filterDefinitions).forEach(([key, definition]) => {
    $filters[key] = definition.value;
  });
  setContext('filters', filters);

  const sort = writable(sortBy);
  setContext('sort', sort);

  const page = writable(0);
  const pageSize = writable(pageSizes[0]);
  setContext('pageSize', pageSize);

  const focusedElement = writable('');
  setContext('focusedElement', focusedElement);

  const mediaQueryValues = writable(new Map());
  setContext('mediaQueryValues', mediaQueryValues);

  let rowsCount = 0;
  let rows = [];
  const pageIndex = 0; // first row
  const preferredView = writable('Grid');

  // Set up a callback function that will be called whenever project data
  // is refreshed or loaded from the backend.
  queryManager.subscribe((projects) => {
    // `rows` is reactive, so just update it to trigger a re-render.
    rows = projects;
  });

  let loading = true;
  let isFirstLoad = true;
  let toggleView = 'Grid';
  preferredView.subscribe((value) => {
    toggleView = value;
  });
  const [currentPage, previousPage] = withPrevious(0);
  $: $currentPage = $page;
  let element = '';
  focusedElement.subscribe((value) => {
    element = value;
  });

  /**
   * Load data from QueryManager.
   *
   * @return {Promise<void>}
   *   Empty promise that resolves on content load.*
   */
  async function load() {
    loading = true;
    await queryManager.load($filters, $page, $pageSize, $sort, source);
    rowsCount = queryManager.count;
    loading = false;
    if (!isFirstLoad) {
      const instanceElement = document.querySelector(
        `[data-project-browser-instance-id="${id}"]`,
      );
      if (instanceElement) {
        instanceElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
    isFirstLoad = false;
  }

  /**
   * Load remote data when the Svelte component is mounted.
   */
  onMount(async () => {
    if (MAX_SELECTIONS === 1) {
      $installList = [];
    }

    await load();
    const focus = element ? document.getElementById(element) : false;
    if (focus) {
      focus.focus();
      $focusedElement = '';
    }
  });

  function onPageChange(event) {
    const activePage = document.querySelector(
      `[aria-label="Page ${$page + 1}"]`,
    );
    if (activePage) {
      activePage.focus();
    }
    $page = event.detail.page;
    load();
  }

  function onPageSizeChange() {
    $page = 0;
    load();
  }

  async function onSort(event) {
    $sort = event.detail.sort;
    $page = 0;
    await load();
  }

  async function onFilterChange(event) {
    $page = 0;
    $filters = event.detail.filters;
    await load();
  }

  async function onToggle(val) {
    if (val !== toggleView) toggleView = val;
    preferredView.set(val);
  }

  /**
   * Refreshes the live region after a filter or search completes.
   */
  const refreshLiveRegion = () => {
    if (rowsCount) {
      // Set announce() to an empty string. This ensures the result count will
      // be announced after filtering even if the count is the same.
      announce('');

      // The announcement is delayed by 210 milliseconds, a wait that is
      // slightly longer than the 200 millisecond debounce() built into
      // announce(). This ensures that the above call to reset the aria live
      // region to an empty string actually takes place instead of being
      // debounced.
      setTimeout(() => {
        announce(
          Drupal.t('@count Results for @source, Sorted by @sortText', {
            '@count': numberFormatter.format(rowsCount),
            '@sortText': sorts[$sort],
            '@source': name,
          }),
        );
      }, 210);
    }
  };

  document.onmouseover = function setInnerDocClickTrue() {
    window.innerDocClick = true;
  };

  document.onmouseleave = function setInnerDocClickFalse() {
    window.innerDocClick = false;
  };

  // Handles back button functionality to go back to the previous page the user was on before.
  window.addEventListener('popstate', () => {
    // Confirm the popstate event was a back button action by checking that
    // the user clicked out of the document.
    if (!window.innerDocClick) {
      $page = $previousPage;
      load();
    }
  });

  window.onload = { onFilterChange };
  // Removes initial loader if it exists.
  const initialLoader = document.getElementById('initial-loader');
  if (initialLoader) {
    initialLoader.remove();
  }
</script>

<MediaQuery query="(min-width: 1200px)" let:matches>
  <ProjectGrid {toggleView} {loading} {rows} {pageIndex} {$pageSize} let:rows>
    <div slot="head">
      {#if numberOfFilters > 0 || numberOfSorts > 1}
        <Search
          on:sort={onSort}
          on:FilterChange={onFilterChange}
          {refreshLiveRegion}
          {filterDefinitions}
          {sorts}
        />
      {/if}

      <div class="pb-layout__header">
        <div class="pb-search-results">
          {#if rowsCount}
            {Drupal.formatPlural(
              rowsCount,
              `${numberFormatter.format(1)} Result`,
              `${numberFormatter.format(rowsCount)} Results`,
            )}
          {/if}
        </div>

        {#if matches}
          <div class="pb-display">
            <button
              class:pb-display__button--selected={toggleView === 'List'}
              class="pb-display__button pb-display__button--first"
              value="List"
              aria-pressed={toggleView === 'List'}
              on:click={(e) => {
                toggleView = 'List';
                onToggle(e.target.value);
              }}
            >
              <img
                class="pb-display__button-icon project-browser__list-icon"
                src="{FULL_MODULE_PATH}/images/list.svg"
                alt=""
              />
              {Drupal.t('List')}
            </button>
            <button
              class:pb-display__button--selected={toggleView === 'Grid'}
              class="pb-display__button pb-display__button--last"
              value="Grid"
              aria-pressed={toggleView === 'Grid'}
              on:click={(e) => {
                toggleView = 'Grid';
                onToggle(e.target.value);
              }}
            >
              <img
                class="pb-display__button-icon project-browser__grid-icon"
                src="{FULL_MODULE_PATH}/images/grid-fill.svg"
                alt=""
              />
              {Drupal.t('Grid')}
            </button>
          </div>
        {/if}
      </div>
    </div>
    {#each rows as row (row.id)}
      <Project {toggleView} project={row} />
    {/each}
    <div slot="bottom">
      {#if paginate}
        <Pagination
          options={pageSizes}
          page={$page}
          count={rowsCount}
          on:pageChange={onPageChange}
          on:pageSizeChange={onPageSizeChange}
        />
      {/if}
    </div>
  </ProjectGrid>
  {#if loading}
    <div class="pb-projects__loading-overlay">
      <Loading />
    </div>
  {/if}
</MediaQuery>
