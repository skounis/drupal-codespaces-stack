<?php

namespace Drupal\simple_sitemap_engines\Form\Handler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Form\Handler\BundleEntityFormHandlerTrait;

/**
 * Defines the handler for bundle entity forms.
 */
class BundleEntityFormHandler extends EntityFormHandlerBase {

  use BundleEntityFormHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    if (isset($form['simple_sitemap'])) {
      parent::formAlter($form, $form_state);

      $form['simple_sitemap'] = $this->settingsForm($form['simple_sitemap']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->configFactory
      ->getEditable("simple_sitemap_engines.bundle_settings.$this->entityTypeId.$this->bundleName")
      ->set('index_now', $form_state->getValue('simple_sitemap_index_now'))
      ->save();
  }

}
