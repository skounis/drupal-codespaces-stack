<!-- Media query component based on
 https://svelte.dev/repl/26eb44932920421da01e2e21539494cd?version=3.48.0 -->
<script>
  import { onMount, getContext } from 'svelte';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let query;

  let mql;
  let mqlListener;
  let wasMounted = false;
  let matches = false;

  const mediaQueryValues = getContext('mediaQueryValues');

  // eslint-disable-next-line no-shadow
  function addNewListener(query) {
    mql = window.matchMedia(query);
    mqlListener = (v) => {
      matches = v.matches;
      // Update store values
      const currentMqs = $mediaQueryValues;
      currentMqs.set(query, matches);
      $mediaQueryValues = currentMqs;
    };
    mql.addEventListener('change', mqlListener);
    matches = mql.matches;
    // Set store values on page load
    const mqs = $mediaQueryValues;
    mqs.set(query, matches);
    $mediaQueryValues = mqs;
  }

  function removeActiveListener() {
    if (mql && mqlListener) {
      mql.removeListener(mqlListener);
    }
  }

  onMount(() => {
    wasMounted = true;
    return () => {
      removeActiveListener();
    };
  });

  $: {
    if (wasMounted) {
      removeActiveListener();
      addNewListener(query);
    }
  }
</script>

<slot {matches} />
