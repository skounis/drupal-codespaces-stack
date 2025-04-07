<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\SimpleSitemapType;

/**
 * Form handler for sitemap edit forms.
 */
class SimpleSitemapEntityForm extends EntityForm {

  use AutowireTrait;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface
   */
  protected $entity;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SimpleSitemapEntityForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Include this sitemap during generation'),
      '#default_value' => $this->entity->isEnabled(),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#disabled' => !$this->entity->isNew(),
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => '\Drupal\simple_sitemap\Entity\SimpleSitemap::load',
        'replace_pattern' => '[^a-z0-9-_]+',
        'replace' => '-',
        'error' => $this->t('The sitemap ID will be part of the URL and can only contain lowercase letters, numbers, dashes and underscores.'),
      ],
      '#description' => $this->t('The sitemap ID will be part of the URL and can only contain lowercase letters, numbers, dashes and underscores.'),
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Sitemap type'),
      '#options' => array_map(function ($sitemap_type) {
        return $sitemap_type->label();
      }, SimpleSitemapType::loadMultiple()),
      '#default_value' => !$this->entity->isNew() ? $this->entity->getType()->id() : NULL,
      '#required' => TRUE,
      '#description' => $this->t('The sitemap\'s type defines its looks and content. Sitemaps types can be configured <a href="@url">here</a>.',
        ['@url' => Url::fromRoute('entity.simple_sitemap_type.collection')->toString()]
      ),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#default_value' => $this->entity->get('description'),
      '#title' => $this->t('Administrative description'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->entity->save();

    if ($return === SAVED_UPDATED) {
      $this->messenger()->addStatus($this->t('Sitemap %label has been updated.', ['%label' => $this->entity->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Sitemap %label has been created.', ['%label' => $this->entity->label()]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $return;
  }

}
