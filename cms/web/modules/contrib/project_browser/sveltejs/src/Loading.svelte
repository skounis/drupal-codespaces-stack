<script>
  // eslint-disable-next-line import/prefer-default-export
  export let positionAbsolute = false;
  export let inline = false;
</script>

<div
  class="loading__ajax-progress"
  class:absolute={positionAbsolute}
  class:ajax-progress--fullscreen={!inline}
>
  <div
    class="ajax-progress__throbber"
    class:ajax-progress__throbber--fullscreen={!inline}
  >
    &nbsp;
  </div>
</div>
