<script>
  import { FULL_MODULE_PATH } from './constants';

  import Image from './Project/Image.svelte';

  // eslint-disable-next-line import/no-mutable-exports,import/prefer-default-export
  export let sources;

  const { Drupal } = window;
  let index = 0;

  const missingAltText = () => !!sources.filter((src) => !src.alt).length;

  /**
   * Props for a slide next/previous button.
   *
   * @param {string} dir
   *   The direction of the button.
   * @return {{disabled: boolean, class: string}}
   *   The slide props.
   */
  const buttonProps = (dir) => {
    const isDisabled =
      dir === 'right' ? index === sources.length - 1 : index === 0;

    const classes = [
      'pb-image-carousel__btn',
      `pb-image-carousel__btn--${dir}`,
      isDisabled ? 'pb-image-carousel__btn--disabled' : '',
    ];

    return {
      class: classes.filter((className) => !!className).join(' '),
      disabled: isDisabled,
    };
  };

  /**
   * Props for a slide next/previous button image.
   *
   * @param {string} dir
   *   The direction of the button
   * @return {{src: string, alt: *}}
   *   The slide button Props
   */
  const imgProps = (dir) => ({
    class: 'pb-image-carousel__btn-icon',
    src: `${FULL_MODULE_PATH}/images/slide-icon.svg`,
    alt: dir === 'right' ? Drupal.t('Slide right') : Drupal.t('Slide left'),
  });
</script>

<!-- svelte-ignore a11y-missing-attribute -->
<div class="pb-image-carousel" aria-hidden={missingAltText()}>
  {#if sources.length}
    <button
      on:click={() => {
        index = (index + sources.length - 1) % sources.length;
      }}
      {...buttonProps('left')}><img {...imgProps('left')} /></button
    >
  {/if}
  <Image {sources} {index} class="pb-image-carousel__slide" />
  {#if sources.length}
    <button
      on:click={() => {
        index = (index + 1) % sources.length;
      }}
      {...buttonProps('right')}><img {...imgProps('right')} /></button
    >
  {/if}
</div>
