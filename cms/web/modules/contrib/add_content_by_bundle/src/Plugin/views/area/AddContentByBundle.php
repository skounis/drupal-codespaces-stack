<?php

namespace Drupal\add_content_by_bundle\Plugin\views\area;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area plugin to display a bundle-specific node/add link.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("add_content_by_bundle")
 */
class AddContentByBundle extends AreaPluginBase {
  use RedirectDestinationTrait;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccessManagerInterface $access_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->accessManager = $access_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('access_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['bundle'] = ['default' => NULL];
    $options['label'] = ['default' => NULL];
    $options['class'] = ['default' => NULL];
    $options['target'] = ['default' => ''];
    $options['width'] = ['default' => '600'];
    $options['form_mode'] = ['default' => NULL];
    $options['params'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Use the entity_type defined for the view.
    $entity_type = $this->view->getBaseEntityType()->id();
    // Get all bundle types for our entity type.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
    // Assembled an options list of the bundles.
    $bundlesList = [];
    foreach ($bundles as $id => $bundle) {
      $label = $bundle['label'];
      $bundlesList[$id] = $label;
    }
    // New content bundle type.
    // @todo preselect if a single bundle specified for the view?
    $form['bundle'] = [
      '#title' => $this->t('Add content bundle (Content) type'),
      '#description' => $this->t('The bundle (content) type of content to add.'),
      '#type' => 'select',
      '#options' => $bundlesList,
      '#default_value' => (!empty($this->options['bundle'])) ? $this->options['bundle'] : '',
      '#required' => TRUE,
    ];
    // If the Form Mode Control module is installed, expose an option to use it.
    if (\Drupal::service('module_handler')->moduleExists('form_mode_control')) {
      $form_modes = \Drupal::service('entity_display.repository')->getFormModeOptions($entity_type);
      // Only expose the form element if our entity type has more than one
      // form mode.
      if ($form_modes && is_array($form_modes) && count($form_modes) > 1) {
        $form['form_mode'] = [
          '#title' => $this->t('Form mode'),
          '#description' => $this->t('The form mode to use for adding an entity.'),
          '#type' => 'select',
          '#options' => $form_modes,
          '#default_value' => (!empty($this->options['form_mode'])) ? $this->options['form_mode'] : '',
        ];
      }
    }
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#description' => $this->t('The text of the link.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['label'] ?: $this->t('Add a new entry'),
      '#required' => TRUE,
    ];
    $form['class'] = [
      '#title' => $this->t('Class'),
      '#description' => $this->t('A CSS class to apply to the link. If using multiple classes, separate them by spaces.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['class'],
    ];
    $form['target'] = [
      '#title' => $this->t('Target'),
      '#description' => $this->t('Optionally have the form open on-page in a modal or off-canvas dialog.'),
      '#type' => 'select',
      '#default_value' => $this->options['target'],
      '#options' => [
        '' => $this->t('Default'),
        'tray' => $this->t('Off-Screen Tray'),
        'modal' => $this->t('Modal Dialog'),
      ],
    ];
    $form['width'] = [
      '#title' => $this->t('Dialog Width'),
      '#description' => $this->t('How wide the dialog should appear.'),
      '#type' => 'number',
      '#min' => '100',
      '#default_value' => $this->options['width'],
      '#states' => [
        // Show this number field only if a dialog is chosen above.
        'invisible' => [
          ':input[name="options[target]"]' => ['value' => ''],
        ],
      ],
    ];
    $form['params'] = [
      '#title' => $this->t('Additional Parameters'),
      '#description' => $this->t("List any additional paramters, separating the key and value with a pipe (|). The use of tokens for the view\'s arguments is supported. An example is {{ arguments.user_id }}."),
      '#type' => 'textarea',
      '#default_value' => $this->options['params'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();

    // Validate that the specified form mode exists for the specified bundle.
    if ($this->options['form_mode']) {
      $entity_type = $this->view->getBaseEntityType()->id();
      $form_modes = \Drupal::service('entity_display.repository')->getFormModeOptionsByBundle($entity_type, $this->options['bundle']);
      if (!isset($form_modes[$this->options['form_mode']])) {
        $errors[] = $this
          ->t('%current_display: The %form_mode form display is not defined for the %bundle type.', [
            '%current_display' => $this->displayHandler->display['display_title'],
            '%form_mode' => $this->options['form_mode'],
            '%bundle' => $this->options['bundle'],
          ]);
        return $errors;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $account = \Drupal::currentUser();
    if ($empty && empty($this->options['bundle'])) {
      return [];
    }
    $bundle_type = $this->options['bundle'];
    $entity_type = $this->view->getBaseEntityType();
    // Assemble query params.
    $params = $this->getDestinationArray();
    // If set, add form_mode to URL.
    if (\Drupal::service('module_handler')->moduleExists('form_mode_control') && $form_mode = $this->options['form_mode']) {
      $params['display'] = $form_mode;
    }
    // If configured to add params, parse into our array.
    if ($this->options['params']) {
      $this->extractParams($params, $this->options['params']);
    }

    // Try to be entity-type agnostic.
    if ($entity_type->id() === 'node') {
      // Link to add a node of the specified type, then return to our view.
      $url = Url::fromRoute('node.add', ['node_type' => $bundle_type], ['query' => $params]);
      $access = $this->accessManager->checkNamedRoute('node.add', ['node_type' => $bundle_type], $account);
    }
    elseif ($entity_type->id() === 'taxonomy_term') {
      // Link to add a term of the specified type, then return to our view.
      $url = Url::fromRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $bundle_type], ['query' => $params]);
      $access = $this->accessManager->checkNamedRoute('entity.taxonomy_term.add_form', ['taxonomy_vocabulary' => $bundle_type], $account);
    }
    else {
      // Try to get the entity creation path.
      $entity_links = $entity_type->get('links');
      if (isset($entity_links['add-form'])) {
        // Replace the bundle token with the specified value.
        $path = preg_replace('/\{[_a-z]+\}/', $bundle_type, $entity_links['add-form']);
      }
      elseif (isset($entity_links['add-page'])) {
        $path = str_replace('{' . $entity_type->id() . '}', $bundle_type, $entity_links['add-page']);
      }
      if (empty($path)) {
        // An entity we don't know how to process, so exit.
        // @todo throw a warning?
        return;
      }
      // Prepend the path to make a valid internal URI.
      $path = 'internal:' . $path;
      $url = Url::fromUri($path, ['query' => $params]);
      // Now use the URL to check access.
      $route_name = $url->getRouteName();
      $route_parameters = $url->getrouteParameters();
      $access = $this->accessManager->checkNamedRoute($route_name, $route_parameters, $account);
    }

    // Parse and sanitize provided classes.
    if ($this->options['class']) {
      $classes = explode(' ', $this->options['class']);
      foreach ($classes as $index => $class) {
        $classes[$index] = Html::getClass($class);
      }
    }
    else {
      $classes = [];
    }
    // Assemble elements into a link render array.
    $element = [
      '#type' => 'link',
      '#title' => $this->options['label'],
      '#url' => $url,
      '#options' => [
        'attributes' => ['class' => $classes],
      ],
      '#access' => $access,
    ];
    // Apply the selected dialog options.
    if ($this->options['target']) {
      $element['#options']['attributes']['class'][] = 'use-ajax';
      $width = $this->options['width'] ?: 600;
      $element['#options']['attributes']['data-dialog-options'] = Json::encode(['width' => $width]);
      switch ($this->options['target']) {
        case 'tray':
          $element['#options']['attributes']['data-dialog-renderer'] = 'off_canvas';
          $element['#options']['attributes']['data-dialog-type'] = 'dialog';
          break;

        case 'modal':
          $element['#options']['attributes']['data-dialog-type'] = 'modal';
          break;
      }
    }
    return $element;
  }

  /**
   * Parse provided text into key-value pairs, checking for tokens.
   *
   * @param array $params
   *   The array to which parsed values will be added.
   * @param string $input
   *   The configured input to parse.
   */
  protected function extractParams(array &$params, $input) {
    $list = explode("\n", $input);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');
    $display = $this->view->getDisplay();
    // @todo possible to support additional tokens?
    $tokens = $display->getArgumentsTokens();
    foreach ($list as $text) {

      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\\|(.*)/', $text, $matches)) {

        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
      }
      elseif (strlen($text) <= 255) {
        $key = $value = $text;
      }
      // Check for tokens in the value.
      if ($tokens) {
        $value = $display->viewsTokenReplace($value, $tokens);
      }
      $params[$key] = $value;
    }
  }

}
