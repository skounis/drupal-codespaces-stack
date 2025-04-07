/**
 * @file
 * Defines the behavior of the entity settings form.
 */

((Drupal, once) => {
  /**
   * The behavior of the entity settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to the form.
   */
  Drupal.behaviors.simpleSitemapEntities = {
    attach(context) {
      once(
        'simple-sitemap-entities',
        'table tr input[type=checkbox][checked]',
        context,
      ).forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
          const row = event.target.closest('tr');
          const table = event.target.closest('table');

          row.classList.toggle('color-success');
          row.classList.toggle('color-warning');

          const messages = new Drupal.Message();
          const id = 'simple-sitemap-entities-warning';
          const showMessage = table.querySelector('tr.color-warning') !== null;
          const messageExists = messages.select(id) !== null;

          if (showMessage && !messageExists) {
            messages.add(
              Drupal.t(
                'The sitemap settings and any per-entity overrides will be deleted for the unchecked entity types.',
              ),
              { id, type: 'warning' },
            );
          } else if (!showMessage && messageExists) {
            messages.remove(id);
          }
        });
      });
    },
  };
})(Drupal, once);
