<script>
  import { afterUpdate } from 'svelte';

  // eslint-disable-next-line import/prefer-default-export
  export let tasks = [];

  const { Drupal } = window;

  // Toggle the dropdown visibility for the clicked drop button
  const toggleDropdown = (event) => {
    const wrapper = event.currentTarget.closest('.dropbutton-wrapper');
    const isOpen = wrapper.classList.contains('open');

    // Close all open dropdowns first
    document.querySelectorAll('.dropbutton-wrapper.open').forEach((el) => {
      el.classList.remove('open');
    });

    if (!isOpen) {
      wrapper.classList.add('open');
    }
  };

  // Handle keydown for closing the dropdown with Escape
  const handleKeyDown = (event) => {
    // Query the DOM for getting the only opened dropbutton.
    const openDropdown = document.querySelector('.dropbutton-wrapper.open');
    if (!openDropdown) return;

    // If there are no items in the dropdown, exit early
    if (!openDropdown.querySelectorAll('.secondary-action a').length) return;

    const toggleButton = openDropdown.querySelector('.dropbutton__toggle');
    if (event.key === 'Escape') {
      openDropdown.classList.remove('open');
      toggleButton.focus();
    }
  };

  // Close the dropdown if clicked outside
  const closeDropdownOnOutsideClick = (event) => {
    document.querySelectorAll('.dropbutton-wrapper.open').forEach((wrapper) => {
      if (!wrapper.contains(event.target)) {
        wrapper.classList.remove('open');
      }
    });
  };
  document.addEventListener('click', closeDropdownOnOutsideClick);
  document.addEventListener('keydown', handleKeyDown);

  let thisElement;
  afterUpdate(() => {
    Drupal.attachBehaviors(thisElement);
  });
</script>

<div
  class="dropbutton-wrapper dropbutton-multiple"
  data-once="dropbutton"
  bind:this={thisElement}
>
  <div class="dropbutton-widget">
    <ul class="dropbutton dropbutton--extrasmall dropbutton--multiple">
      <li class="dropbutton__item dropbutton-action">
        <a
          class:use-ajax={tasks[0].ajax}
          href={tasks[0].url}
          on:click={() => {}}
          class="pb__action_button"
        >
          {tasks[0].text}
        </a>
      </li>

      {#if tasks.length > 1}
        <li class="dropbutton-toggle">
          <button
            type="button"
            class="dropbutton__toggle"
            on:click={toggleDropdown}
          >
            <span class="visually-hidden">List additional actions</span>
          </button>
        </li>

        {#each tasks.slice(1) as task}
          <li class="dropbutton__item dropbutton-action secondary-action">
            <a class:use-ajax={task.ajax} href={task.url}>{task.text}</a>
          </li>
        {/each}
      {/if}
    </ul>
  </div>
</div>
