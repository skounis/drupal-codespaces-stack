<?php

namespace Drupal\simple_sitemap\Form\Handler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Form\FormHelper;

/**
 * Defines the handler for entity forms.
 */
class EntityFormHandler extends EntityFormHandlerBase {

  use EntityFormHandlerTrait;

  /**
   * {@inheritdoc}
   */
  protected $operations = ['default', 'edit', 'add', 'register'];

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    parent::formAlter($form, $form_state);

    $form['simple_sitemap']['#description'] = $this->t('Settings for this entity can be overridden here.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form): array {
    $form = parent::settingsForm($form);

    $settings = $this->getSettings();
    $bundle_label = $this->entityHelper
      ->getBundleLabel($this->entityTypeId, $this->bundleName);

    foreach ($this->generator->entityManager()->setSitemaps()->getSitemaps() as $variant => $sitemap) {
      $variant_form = &$form[$variant];

      $variant_form['index']['#options'] = [
        $this->t('Do not index this <em>@bundle</em> entity in sitemap <em>@sitemap</em>', [
          '@bundle' => $bundle_label,
          '@sitemap' => $sitemap->label(),
        ]),
        $this->t('Index this <em>@bundle</em> entity in sitemap <em>@sitemap</em>', [
          '@bundle' => $bundle_label,
          '@sitemap' => $sitemap->label(),
        ]),
      ];

      // Disable fields of entity instance whose bundle is not indexed.
      $variant_form['#disabled'] = empty($settings[$variant]['bundle_settings']['index']);

      $variant_form['priority']['#description'] = $this->t('The priority this <em>@bundle</em> entity will have in the eyes of search engine bots.', ['@bundle' => $bundle_label]);
      $variant_form['changefreq']['#description'] = $this->t('The frequency with which this <em>@bundle</em> entity changes. Search engine bots may take this as an indication of how often to index it.', ['@bundle' => $bundle_label]);
      $variant_form['include_images']['#description'] = $this->t('Determines if images referenced by this <em>@bundle</em> entity should be included in the sitemap.', ['@bundle' => $bundle_label]);

      // Mark the default option.
      if (isset($settings[$variant]['bundle_settings']['index'])) {
        $value = (int) $settings[$variant]['bundle_settings']['index'];

        if (isset($variant_form['index']['#options'][$value])) {
          $variant_form['index']['#options'][$value] .= ' <em>(' . $this->t('default') . ')</em>';
        }
      }

      // Mark the default option.
      if (isset($settings[$variant]['bundle_settings']['priority'])) {
        $value = FormHelper::formatPriority($settings[$variant]['bundle_settings']['priority']);

        if (isset($variant_form['priority']['#options'][$value])) {
          $variant_form['priority']['#options'][$value] .= ' (' . $this->t('default') . ')';
        }
      }

      // Mark the default option.
      if (isset($settings[$variant]['bundle_settings']['changefreq'])) {
        $value = $settings[$variant]['bundle_settings']['changefreq'];

        if (isset($variant_form['changefreq']['#options'][$value])) {
          $variant_form['changefreq']['#options'][$value] .= ' (' . $this->t('default') . ')';
        }
        elseif ($value === '' && isset($variant_form['changefreq']['#empty_option'])) {
          $variant_form['changefreq']['#empty_option'] .= ' (' . $this->t('default') . ')';
        }
      }

      // Mark the default option.
      if (isset($settings[$variant]['bundle_settings']['include_images'])) {
        $value = (int) $settings[$variant]['bundle_settings']['include_images'];

        if (isset($variant_form['include_images']['#options'][$value])) {
          $variant_form['include_images']['#options'][$value] .= ' (' . $this->t('default') . ')';
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Make sure the entity is saved first for multi-step forms,
    // see https://www.drupal.org/project/simple_sitemap/issues/3080510.
    if ($this->entity->isNew()) {
      return;
    }

    $entity_manager = $this->generator->entityManager();
    foreach ($entity_manager->setSitemaps()->getSitemaps() as $variant => $sitemap) {
      $settings = $form_state->getValue(['simple_sitemap', $variant]);

      // Variants may have changed since form load.
      if ($settings) {
        $entity_manager
          ->setSitemaps($sitemap)
          ->setEntityInstanceSettings($this->entityTypeId, $this->entity->id(), $settings);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getSettings(): array {
    if (!isset($this->settings)) {
      // New menu link's id is '' instead of NULL, hence checking for empty.
      $entity_id = !$this->entity->isNew() ? $this->entity->id() : NULL;

      // @todo Simplify after getEntityInstanceSettings() works with multiple variants.
      foreach (parent::getSettings() as $variant => $settings) {
        if (NULL !== $entity_id) {
          $this->settings[$variant] = $this->generator
            ->entityManager()
            ->setSitemaps($variant)
            ->getEntityInstanceSettings($this->entityTypeId, $entity_id)[$variant];
        }
        $this->settings[$variant]['bundle_settings'] = $settings;
      }
    }
    return $this->settings;
  }

}
