<?php

namespace Drupal\simple_sitemap\Form\Handler;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\EntityHelper;
use Drupal\simple_sitemap\Form\FormHelper;
use Drupal\simple_sitemap\Manager\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for altering an entity form.
 */
abstract class EntityFormHandlerBase implements EntityFormHandlerInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The sitemap generator service.
   *
   * @var \Drupal\simple_sitemap\Manager\Generator
   */
  protected $generator;

  /**
   * Helper class for working with entities.
   *
   * @var \Drupal\simple_sitemap\Entity\EntityHelper
   */
  protected $entityHelper;

  /**
   * Helper class for working with forms.
   *
   * @var \Drupal\simple_sitemap\Form\FormHelper
   */
  protected $formHelper;

  /**
   * The entity being used by this form handler.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $entity;

  /**
   * The entity type ID.
   *
   * @var string|null
   */
  protected $entityTypeId;

  /**
   * The bundle name.
   *
   * @var string|null
   */
  protected $bundleName;

  /**
   * The sitemap settings.
   *
   * @var array|null
   */
  protected $settings;

  /**
   * Supported form operations.
   *
   * @var array
   */
  protected $operations = ['default', 'edit', 'add'];

  /**
   * EntityFormHandlerBase constructor.
   *
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Entity\EntityHelper $entity_helper
   *   Helper class for working with entities.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   */
  public function __construct(Generator $generator, EntityHelper $entity_helper, FormHelper $form_helper) {
    $this->generator = $generator;
    $this->entityHelper = $entity_helper;
    $this->formHelper = $form_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.form_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $this->processFormState($form_state);

    $form['simple_sitemap'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Simple XML Sitemap'),
      '#attributes' => ['class' => ['simple-sitemap-fieldset']],
      '#tree' => TRUE,
      '#weight' => 10,
    ];

    // Only attach fieldset summary js to 'additional settings' vertical tabs.
    if (isset($form['additional_settings'])) {
      $form['simple_sitemap']['#attached']['library'][] = 'simple_sitemap/fieldsetSummaries';
      $form['simple_sitemap']['#group'] = 'additional_settings';
    }

    $form['simple_sitemap'] = $this->settingsForm($form['simple_sitemap']);
    $form['simple_sitemap'] = $this->formHelper->regenerateNowForm($form['simple_sitemap']);

    $this->addSubmitHandlers($form, [$this, 'submitForm'],
      [$this->formHelper, 'regenerateNowFormSubmit']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form): array {
    $sitemaps = $this->generator->entityManager()->getSitemaps();
    $settings = $this->getSettings();
    if ($sitemaps) {
      $form['#markup'] = '<strong>' . $this->t('Sitemaps') . '</strong>';
    }
    else {
      $form['#markup'] = $this->t('At least one sitemap needs to be defined for a bundle to be indexable.<br>Sitemaps can be configured <a href="@url">here</a>.',
        ['@url' => Url::fromRoute('entity.simple_sitemap.collection')->toString()]
      );
    }

    foreach ($sitemaps as $variant => $sitemap) {
      if ($settings[$variant]) {
        $variant_form = &$form[$variant];

        $variant_form = [
          '#type' => 'details',
          '#title' => '<em>' . $sitemap->label() . '</em>',
          '#open' => !empty($settings[$variant]['index']),
        ];

        $variant_form = $this->formHelper
          ->settingsForm($variant_form, $settings[$variant]);

        $variant_form['index']['#attributes']['data-simple-sitemap-label'] = $sitemap->label();
        $variant_form['index']['#type'] = 'radios';
        $variant_form['index']['#title'] = NULL;

        $variant_form['index']['#options'] = [
          $this->t('Do not index entities of this type in sitemap <em>@sitemap</em>', ['@sitemap' => $sitemap->label()]),
          $this->t('Index entities of this type in sitemap <em>@sitemap</em>', ['@sitemap' => $sitemap->label()]),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->processFormState($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): ?string {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleName(): ?string {
    return $this->bundleName;
  }

  /**
   * {@inheritdoc}
   */
  public function isSupportedOperation(string $operation): bool {
    return in_array($operation, $this->operations, TRUE);
  }

  /**
   * Retrieves data from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \InvalidArgumentException
   *   In case the form is not an entity form.
   */
  protected function processFormState(FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();

    if (!$form_object instanceof EntityFormInterface) {
      throw new \InvalidArgumentException('Invalid form state');
    }

    $this->setEntity($form_object->getEntity());
  }

  /**
   * Gets the sitemap settings.
   *
   * @return array
   *   The sitemap settings.
   */
  protected function getSettings(): array {
    if (!isset($this->settings)) {
      $this->settings = $this->generator
        ->entityManager()
        ->setSitemaps()
        ->getBundleSettings($this->entityTypeId, $this->bundleName);
    }

    return $this->settings;
  }

  /**
   * Adds the submit handlers to the structured form array.
   *
   * @param array $element
   *   An associative array containing the structure of the current element.
   * @param callable ...$handlers
   *   The submit handlers to add.
   */
  protected function addSubmitHandlers(array &$element, callable ...$handlers) {
    // Add new handlers only if a handler for the 'save' action is present.
    if (!empty($element['#submit']) && in_array('::save', $element['#submit'], TRUE)) {
      array_push($element['#submit'], ...$handlers);
    }

    // Process child elements.
    foreach (Element::children($element) as $key) {
      $this->addSubmitHandlers($element[$key], ...$handlers);
    }
  }

}
