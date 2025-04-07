<script>
  import { FULL_MODULE_PATH } from '../constants';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let sources;
  export let index = 0;

  const normalizedSources = sources ? [sources].flat() : [];
  const { Drupal } = window;
  const fallbackImage = `${FULL_MODULE_PATH}/images/puzzle-piece-placeholder.svg`;
  const showFallback = (ev) => {
    ev.target.src = fallbackImage;
  };

  /**
   * Props for the images used in the carousel.
   *
   * @param {string} src
   *   The source attribute.
   * @param {string} alt
   *   The alt attribute, defaults to 'Placeholder' if undefined.
   *
   * @return {{src, alt: string, class: string}}
   *   An object of element attributes
   */
  const defaultImgProps = (src, alt) => ({
    src,
    alt: typeof alt !== 'undefined' ? alt : Drupal.t('Placeholder'),
    class: `${$$props.class} `,
  });
</script>

<!-- svelte-ignore a11y-missing-attribute -->
{#if normalizedSources.length > index}
  <img
    src={normalizedSources[index].file}
    alt={normalizedSources[index].alt}
    on:error={showFallback}
    class={$$props.class}
  />
{:else}
  <img {...defaultImgProps(fallbackImage)} />
{/if}
