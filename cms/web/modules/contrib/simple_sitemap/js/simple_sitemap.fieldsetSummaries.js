/**
 * @file
 * Attaches simple_sitemap behaviors to the entity form.
 */
(($, Drupal) => {
  Drupal.behaviors.simpleSitemapFieldsetSummaries = {
    attach(context) {
      $(context)
        .find('.simple-sitemap-fieldset')
        .drupalSetSummary((fieldset) => {
          let summary = '';
          const enabledVariants = [];

          $(fieldset)
            .find('input:checkbox[name*="simple_sitemap_index_now"]')
            .each(function each() {
              summary = `${
                this.checked
                  ? Drupal.t('IndexNow notification enabled')
                  : Drupal.t('IndexNow notification disabled')
              }, `;
            });

          $(fieldset)
            .find('input:radio:checked[data-simple-sitemap-label][value="1"]')
            .each(function each() {
              enabledVariants.push(this.dataset.simpleSitemapLabel);
            });

          if (enabledVariants.length > 0) {
            summary += Drupal.t('Included in sitemaps: ');
            summary += enabledVariants.join(', ');
          } else {
            summary += Drupal.t('Excluded from all sitemaps');
          }

          return summary;
        });
    },
  };
})(jQuery, Drupal);
