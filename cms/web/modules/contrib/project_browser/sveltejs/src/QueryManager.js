import { get, writable } from 'svelte/store';
import { BASE_URL } from './constants';

// This is the single source of truth for all projects that have been loaded from
// the backend. It is keyed by fully qualified project ID, and shared by all
// QueryManager instances.
const cache =  writable({});

function getFromCache (projects) {
  const cacheData = get(cache);
  return projects
    .map(id => cacheData[id])
    .filter(item => typeof item === 'object');
}

function updateProjectsInCache (projects) {
  // Use `.update()` so that all subscribers (i.e., individual QueryManager
  // instances) will be notified and receive the latest project data.
  cache.update((cacheData) => {
    projects.forEach((project) => {
      cacheData[project.id] = project;
    });
    return cacheData;
  });
}

// Allow cached projects to be updated via AJAX.
Drupal.AjaxCommands.prototype.refresh_projects = (ajax, { projects }) => {
  updateProjectsInCache(projects);
};

/**
 * Handles fetching and temporarily caching project data from the backend.
 *
 * This implements a volatile, centralized caching mechanism, ensuring that
 * all instances of the Project Browser on a single page share a consistent
 * source of truth for project data.
 *
 * The cache lives in memory and is reset upon page reload.
 */
export default class {
  constructor (paginated) {
    // If pagination is disabled, then the number of results returned from the
    // first page is, effectively, the total number of results.
    this.paginated = paginated;

    // A list of project IDs that were returned by the last query. These are
    // only the project IDs; the most current data for each of them is stored
    // in the static cache.
    this.list = [];
    // The subscribers that are listening for changes in the projects.
    this.subscribers = [];
    // The total (i.e., not paginated) number of results returned by the most
    // recent query.
    this.count = 0;

    // Whenever the cache changes, we want to notify our subscribers about any
    // changes to the projects we have most recently loaded.
    cache.subscribe(() => {
      const projects = getFromCache(this.list);

      this.subscribers.forEach((callback) => {
        callback(projects);
      });
    });

    this.lastQueryParams = null;
  }

  subscribe (callback) {
    const index = this.subscribers.length;
    this.subscribers.push(callback);

    // The store contract requires us to immediately call the new subscriber.
    callback(getFromCache(this.list));

    // The store contract requires us to return an unsubscribe function.
    return () => {
      this.subscribers.splice(index, 1);
    };
  }

  /**
   * Fetch projects from the backend and store them in memory.
   *
   * @param {Object} filters - The filters to apply in the request.
   * @param {Number} page - The current page number.
   * @param {Number} pageSize - Number of items per page.
   * @param {String} sort - Sorting method.
   * @param {String} source - Data source.
   * @return {Promise<Object>} - The list of project objects.
   */
  async load(filters, page, pageSize, sort, source) {
    // Encode the current filter values as URL parameters.
    const searchParams = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (typeof value === 'boolean') {
        value = Number(value).toString();
      }
      searchParams.set(key, value);
    });
    searchParams.set('page', page);
    searchParams.set('limit', pageSize);
    searchParams.set('sort', sort);
    searchParams.set('source', source);

    const queryString = searchParams.toString();

    if (this.lastQueryParams === queryString) {
      return;
    }
    // We're going to query the backend, so reinitialize our internal state.
    this.list = [];
    this.count = 0;
    this.lastQueryParams = queryString;

    const res = await fetch(
      `${BASE_URL}project-browser/data/project?${queryString}`,
    );
    if (!res.ok) {
      return;
    }

    const { error, list: fetchedList, totalResults } = await res.json();
    if (error && error.length) {
      new Drupal.Message().add(error, { type: 'error' });
    }

    fetchedList.forEach((project) => {
      this.list.push(project.id);
    });
    this.count = this.paginated ? totalResults : fetchedList.length;

    updateProjectsInCache(fetchedList);
  }
}
