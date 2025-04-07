<?php

namespace Drupal\yoast_seo\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\yoast_seo\EntityAnalyser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for yoast_seo_preview form handlers.
 */
class AnalysisFormHandler implements EntityHandlerInterface {

  use DependencySerializationTrait;

  /**
   * The entity analyser.
   *
   * @var \Drupal\yoast_seo\EntityAnalyser
   */
  protected $entityAnalyser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The yoast_seo.settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * SeoPreviewFormHandler constructor.
   *
   * @param \Drupal\yoast_seo\EntityAnalyser $entity_analyser
   *   The entity analyser.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    EntityAnalyser $entity_analyser,
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityAnalyser = $entity_analyser;
    $this->messenger = $messenger;
    $this->config = $config_factory->get('yoast_seo.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('yoast_seo.entity_analyser'),
      $container->get('messenger'),
      $container->get('config.factory')
    );
  }

  /**
   * Ajax Callback for returning entity preview to seo library.
   *
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function analysisSubmitAjax(array &$form, FormStateInterface $form_state) {
    $preview_entity = $form_state->getTemporaryValue('preview_entity');
    $preview_entity->in_preview = TRUE;

    /** @var array{render_theme: ?string, render_view_mode: string} $yoast_settings */
    $yoast_settings = $form_state->get('yoast_settings') ?? [
      'render_theme' => NULL,
      'render_view_mode' => 'default',
    ];

    $entity_data = $this->entityAnalyser->createEntityPreview(
      $preview_entity,
      $yoast_settings['render_theme'],
      $yoast_settings['render_view_mode']
    );

    // The current value of the alias field, if any,
    // takes precedence over the entity url.
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['path'][0]['alias'])) {
      $entity_data['url'] = $user_input['path'][0]['alias'];
    }

    // Any form errors were displayed when our form with the analysis was
    // rendered. Any new messages are from form validation. We don't want to
    // leak those to the user because they'll get them during normal submission
    // so we clear them here.
    $this->messenger->deleteAll();

    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand(
      'body',
      'trigger',
      ['updateSeoData', $entity_data])
    );
    return $response;
  }

  /**
   * Adds yoast_seo_preview submit.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addAnalysisSubmit(array &$element, FormStateInterface $form_state) {
    // Tell the form API not to change the build id as this causes issues with
    // other modules like paragraphs due to the form cache. See
    // https://www.drupal.org/project/yoast_seo/issues/2992284#comment-13134728
    // This must be called here because in `analysisSubmitAjax` it would be too
    // late.
    $triggeringElement = $form_state->getTriggeringElement();
    if ($triggeringElement !== NULL && end($triggeringElement['#parents']) === 'yoast_seo_preview_button') {
      $form_state->addRebuildInfo(
        'copy',
        [
          '#build_id' => TRUE,
        ]
      );
    }

    $element['yoast_seo_preview_button'] = [
      '#type' => 'button',
      '#value' => t('Seo preview'),
      '#attributes' => [
        'class' => ['yoast-seo-preview-submit-button'],
      ],
      '#ajax' => [
        'callback' => [$this, 'analysisSubmitAjax'],
      ],
      // Add a validate step for the button.
      // This will be called as latest validation callback.
      // We can use that call to build our entity and save it temporary to be
      // processed by analysisSubmitAjax callback.
      '#validate' => [[$this, 'cacheProcessedEntityForPreview']],
    ];

    $auto_refresh_seo_result = $this->config->get('auto_refresh_seo_result');
    if ($auto_refresh_seo_result) {
      // Inline styles are bad but we can't reliably use class order here.
      $element['yoast_seo_preview_button']['#attributes']['style'] = 'display: none';
    }
  }

  /**
   * Validation callback for the yoast_seo_preview_button.
   *
   * This is misused to build the entity for previewing. After the validation
   * the form state is rebuilt and we end up with unprocessed values, which
   * cannot be used to build the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function cacheProcessedEntityForPreview(array $form, FormStateInterface $form_state) {
    // Prevent firing accidental submissions from entity builder callbacks.
    $form_state->setTemporaryValue('entity_validated', FALSE);
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface, "Calling " . __FUNCTION__ . " for a form that's not an entity form is invalid.");
    $preview_entity = $form_object->buildEntity($form, $form_state);
    $form_state->setTemporaryValue('preview_entity', $preview_entity);
  }

}
