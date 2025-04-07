<?php

namespace Drupal\ai_image_alt_text\Form;

use Drupal\ai\Enum\AiModelCapability;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Image Alt Text module.
 */
class AiImageAltTextSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_image_alt_text.settings';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->providerManager = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_translate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load config.
    $config = $this->config(static::CONFIG_NAME);

    $form['prompt'] = [
      '#title' => $this->t('Alt text generation prompt'),
      '#type' => 'textarea',
      '#default_value' => $config->get('prompt') ?? '',
      '#description' => $this->t('Prompt used for generating the alt text.'),
      '#required' => TRUE,
    ];
    $form['longer_description'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Prompt is rendered using Twig rendering engine and supports the following tokens:'),
        '{{ entity_lang_name }} - ' . $this->t('Human readable name of the entity language'),
      ],
    ];

    // Make a select with all the image styles availabe.
    $imageStyles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    $imageStylesOptions = [];
    foreach ($imageStyles as $imageStyle) {
      $imageStylesOptions[$imageStyle->id()] = $imageStyle->label();
    }
    $form['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#options' => $imageStylesOptions,
      '#default_value' => $config->get('image_style') ?? 'ai_image_alt_text',
      '#empty_option' => $this->t('Original (NOT RECOMMENDED)'),
      '#description' => $this->t('Image style to use before sending the image to reduce resolution and reformat as PNG. Smaller resolutions saves costs, but may affect the quality of the generated alt text. Leave empty to send original, but note that not all providers takes all resolutions/formats.'),
    ];

    $models = $this->providerManager->getSimpleProviderModelOptions('chat', TRUE, TRUE, [
      AiModelCapability::ChatWithImageVision,
    ]);

    $form['ai_model'] = [
      '#title' => $this->t('AI provider/model'),
      '#type' => 'select',
      '#options' => $models,
      '#default_value' => $config->get('ai_model') ?? '',
      '#empty_option' => $this->t('Use Default Image Vision Model'),
      '#description' => $this->t('AI model to use for generating the alt text.'),
    ];

    $form['autogenerate'] = [
      '#title' => $this->t('Autogenerate on upload'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('autogenerate') ?? FALSE,
      '#description' => $this->t('Automatically generate alt text when uploading an image, without needing action from the user.'),
    ];

    $form['hide_button'] = [
      '#title' => $this->t('Hide button'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('hide_button') ?? FALSE,
      '#description' => $this->t('Hide the button to generate alt text manually.'),
      '#states' => [
        'visible' => [
          ':input[name="autogenerate"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('prompt', $form_state->getValue('prompt'))
      ->set('image_style', $form_state->getValue('image_style'))
      ->set('ai_model', $form_state->getValue('ai_model'))
      ->set('autogenerate', $form_state->getValue('autogenerate'))
      ->set('hide_button', $form_state->getValue('autogenerate') == FALSE ? FALSE : $form_state->getValue('hide_button'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
