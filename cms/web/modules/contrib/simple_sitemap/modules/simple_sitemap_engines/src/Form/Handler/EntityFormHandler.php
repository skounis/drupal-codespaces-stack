<?php

namespace Drupal\simple_sitemap_engines\Form\Handler;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\simple_sitemap\Form\Handler\EntityFormHandlerTrait;
use Drupal\simple_sitemap_engines\Form\SimplesitemapEnginesForm;

/**
 * Defines the handler for entity forms.
 */
class EntityFormHandler extends EntityFormHandlerBase {

  use EntityFormHandlerTrait;

  /**
   * {@inheritdoc}
   */
  protected $operations = ['default', 'edit', 'add', 'register', 'delete'];

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    parent::formAlter($form, $form_state);
    $form = $this->settingsForm($form);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form): array {
    $form = parent::settingsForm($form);

    $form['simple_sitemap_index_now']['#title'] = $this->t('Notify IndexNow search engines of changes <em>now</em>');
    $form['simple_sitemap_index_now']['#description'] = $this->t('Send change notice to IndexNow compatible search engines right after submitting this form.');

    // Sensibly place the IndexNow checkbox.
    $form['simple_sitemap_index_now']['#group'] = 'footer';

    if ($this->entity instanceof EntityPublishedInterface) {
      $status_key = $this->entity->getEntityType()->getKey('status');

      if ($status_key !== FALSE && isset($form[$status_key])) {
        $index_now = $form['simple_sitemap_index_now']['#default_value'];

        if (isset($form[$status_key]['#weight'])) {
          $form['simple_sitemap_index_now']['#weight'] = $form[$status_key]['#weight'] + 10;
        }

        // If existing form entity is unpublished on load, assume it is a draft
        // and uncheck IndexNow. Check IndexNow when changing publishing status.
        if (!$this->entity->isNew() && !$this->entity->isPublished() && $index_now) {
          $selector = ':input[name="' . $status_key . '[value]"]';

          $form['simple_sitemap_index_now']['#default_value'] = 0;
          $form['simple_sitemap_index_now']['#states'] = [
            'checked' => [$selector => ['checked' => TRUE]],
          ];
        }

        // If form entity is new, only check IndexNow when publishing status
        // is checked.
        if ($this->entity->isNew() && $index_now) {
          $selector = ':input[name="' . $status_key . '[value]"]';

          $form['simple_sitemap_index_now']['#states'] = [
            'checked' => [$selector => ['checked' => TRUE]],
          ];
        }
      }
    }

    // Remove access to IndexNow override checkbox if no verification key has
    // been added.
    if (SimplesitemapEnginesForm::getKeyLocation() === NULL) {
      $form['simple_sitemap_index_now']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->entity->_simple_sitemap_index_now = (bool) $form_state->getValue('simple_sitemap_index_now');
  }

  /**
   * {@inheritdoc}
   */
  protected function addSubmitHandlers(array &$element, callable ...$handlers) {
    if (!empty($element['#submit'])) {
      array_unshift($element['#submit'], ...$handlers);
    }

    // Process child elements.
    foreach (Element::children($element) as $key) {
      $this->addSubmitHandlers($element[$key], ...$handlers);
    }
  }

}
