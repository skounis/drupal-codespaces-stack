<?php

namespace Drupal\simple_sitemap\Form\Handler;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the handler for bundle entity forms.
 */
class BundleEntityFormHandler extends EntityFormHandlerBase {

  use BundleEntityFormHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form): array {
    $form = parent::settingsForm($form);

    if ($this->bundleName !== NULL) {
      $bundle_label = $this->entityHelper
        ->getBundleLabel($this->entityTypeId, $this->bundleName);
    }

    foreach ($this->generator->entityManager()->getSitemaps() as $variant => $sitemap) {
      $variant_form = &$form[$variant];

      if (isset($bundle_label)) {
        $variant_form['index']['#options'] = [
          $this->t('Do not index entities of type <em>@bundle</em> in sitemap <em>@sitemap</em>', [
            '@bundle' => $bundle_label,
            '@sitemap' => $sitemap->label(),
          ]),
          $this->t('Index entities of type <em>@bundle</em> in sitemap <em>@sitemap</em>', [
            '@bundle' => $bundle_label,
            '@sitemap' => $sitemap->label(),
          ]),
        ];
      }

      $variant_form['priority']['#description'] = $this->t('The priority entities of this type will have in the eyes of search engine bots.');
      $variant_form['changefreq']['#description'] = $this->t('The frequency with which entities of this type change. Search engine bots may take this as an indication of how often to index them.');
      $variant_form['include_images']['#description'] = $this->t('Determines if images referenced by entities of this type should be included in the sitemap.');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $entity_manager = $this->generator->entityManager();
    foreach ($entity_manager->getSitemaps() as $variant => $sitemap) {
      $settings = $form_state->getValue(['simple_sitemap', $variant]);

      // Variants may have changed since form load.
      if ($settings) {
        $entity_manager
          ->setSitemaps($sitemap)
          ->setBundleSettings($this->entityTypeId, $this->bundleName, $settings);
      }
    }
  }

}
