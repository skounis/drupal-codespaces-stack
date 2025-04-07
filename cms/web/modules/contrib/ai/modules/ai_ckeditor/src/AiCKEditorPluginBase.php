<?php

namespace Drupal\ai_ckeditor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\Traits\AiCKEditorConfigTrait;
use Drupal\editor\Ajax\EditorDialogSave;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base plugin for AiCKEditorPlugin plugins.
 */
abstract class AiCKEditorPluginBase extends PluginBase implements AiCKEditorPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use AiCKEditorConfigTrait;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiProviderPluginManager $ai_provider_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $account, RequestStack $requestStack, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->aiProviderManager = $ai_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->logger = $logger_factory->get('ai_ckeditor');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $form['description'] = [
      '#markup' => '<p>' . $this->pluginDefinition['description'] . '</p>',
      '#weight' => -9999,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 9999,
    ];

    $form['actions']['generate'] = [
      '#type' => 'button',
      '#value' => $this->t('Generate'),
      '#ajax' => [
        'callback' => [$this, 'ajaxGenerate'],
        'wrapper' => 'ai-ckeditor-response',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Save changes to editor'),
      '#weight' => 9999,
      '#ajax' => [
        'callback' => [$this, 'submitCkEditorModalForm'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();

    // @todo Let plugins define an option to return HTML/Markdown or not and signal that to AI.
    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'value' => $values["plugin_config"]["response_text"]["value"],
        'returnsHtml' => TRUE,
      ],
    ]));

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function availableEditors() {
    return [
      $this->pluginId  => $this->label(),
    ];
  }

}
