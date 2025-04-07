<script>
  const { Drupal } = window;
  import { MAX_SELECTIONS } from '../constants';

  // eslint-disable-next-line import/prefer-default-export
  export let message = Drupal.t('Installing');
</script>

<span class:pb-ellipsis={MAX_SELECTIONS === 1}>{message}</span>
