<script>
  // eslint-disable-next-line import/prefer-default-export
  export let click = () => {};
</script>

<button
  on:click={click}
  class="pb__action_button select_button"
  {...$$restProps}
>
  <slot />
</button>
