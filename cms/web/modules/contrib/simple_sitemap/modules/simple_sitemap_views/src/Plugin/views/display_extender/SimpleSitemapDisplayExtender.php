<?php

namespace Drupal\simple_sitemap_views\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Form\FormHelper;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\display\DisplayRouterInterface;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple XML Sitemap display extender plugin.
 *
 * @ingroup views_display_extender_plugins
 *
 * @ViewsDisplayExtender(
 *   id = "simple_sitemap_display_extender",
 *   title = @Translation("Simple XML Sitemap"),
 *   help = @Translation("Simple XML Sitemap settings for this view."),
 *   no_ui = FALSE
 * )
 */
class SimpleSitemapDisplayExtender extends DisplayExtenderPluginBase {

  /**
   * Helper class for working with forms.
   *
   * @var \Drupal\simple_sitemap\Form\FormHelper
   */
  protected $formHelper;

  /**
   * The sitemaps.
   *
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[]
   */
  protected $sitemaps = [];

  /**
   * Constructs the plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormHelper $form_helper, SimpleSitemapViews $sitemap_views) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formHelper = $form_helper;
    $this->sitemaps = $sitemap_views->getSitemaps();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.form_helper'),
      $container->get('simple_sitemap.views')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    if (!$this->hasSitemapSettings()) {
      $this->options = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['variants'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if ($this->hasSitemapSettings() && $form_state->get('section') == 'simple_sitemap') {
      $has_required_arguments = $this->hasRequiredArguments();
      $arguments_options = $this->getArgumentsOptions();

      $form['variants'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      foreach ($this->sitemaps as $variant => $sitemap) {
        $settings = $this->getSitemapSettings($variant);
        $variant_form = &$form['variants'][$variant];

        $variant_form = [
          '#type' => 'details',
          '#title' => '<em>' . $sitemap->label() . '</em>',
          '#open' => (bool) $settings['index'],
        ];

        $variant_form = $this->formHelper
          ->settingsForm($variant_form, $settings);

        $variant_form['index']['#title'] = $this->t('Index this display in sitemap <em>@variant_label</em>', ['@variant_label' => $sitemap->label()]);
        $variant_form['priority']['#description'] = $this->t('The priority this display will have in the eyes of search engine bots.');
        $variant_form['changefreq']['#description'] = $this->t('The frequency with which this display changes. Search engine bots may take this as an indication of how often to index it.');

        // Images are not supported.
        unset($variant_form['include_images']);

        // Arguments to index.
        $variant_form['arguments'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Indexed arguments'),
          '#options' => $arguments_options,
          '#default_value' => $settings['arguments'],
          '#attributes' => ['class' => ['indexed-arguments']],
          '#access' => !empty($arguments_options),
        ];

        // Required arguments are always indexed.
        foreach ($this->getRequiredArguments() as $argument_id) {
          $variant_form['arguments'][$argument_id]['#disabled'] = TRUE;
        }

        // Max links with arguments.
        $variant_form['max_links'] = [
          '#type' => 'number',
          '#title' => $this->t('Maximum display variations'),
          '#description' => $this->t('The maximum number of link variations to be indexed for this display. If left blank, each argument will create link variations for this display. Use with caution, as a large number of argument values​can significantly increase the number of sitemap links.'),
          '#default_value' => $settings['max_links'],
          '#min' => 1,
          '#access' => !empty($arguments_options) || $has_required_arguments,
        ];
      }

      $form['#title'] .= $this->t('Simple XML Sitemap settings for this display');
      $form['#attached']['library'][] = 'simple_sitemap_views/viewsUi';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    if ($this->hasSitemapSettings() && $form_state->get('section') === 'simple_sitemap') {
      $required_arguments = $this->getRequiredArguments();

      foreach ($this->sitemaps as $variant => $sitemap) {
        $key = ['variants', $variant, 'arguments'];
        $arguments = &$form_state->getValue($key, []);
        $arguments = array_merge($arguments, $required_arguments);
        $errors = $this->validateIndexedArguments($arguments);

        foreach ($errors as $message) {
          $form_state->setError($form['variants'][$variant]['arguments'], $message);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($this->hasSitemapSettings() && $form_state->get('section') === 'simple_sitemap') {
      $variants = $form_state->getValue('variants');
      $this->options['variants'] = [];

      // Save settings for each sitemap.
      foreach ($this->sitemaps as $variant => $sitemap) {
        $settings = $variants[$variant] + $this->getSitemapSettings($variant);

        if ($settings['index']) {
          $settings['arguments'] = array_filter($settings['arguments']);
          $this->options['variants'][$variant] = $settings;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(): array {
    $errors = [parent::validate()];

    // Validate the argument options relative to the
    // current state of the view argument handlers.
    if ($this->hasSitemapSettings()) {
      foreach ($this->sitemaps as $variant => $sitemap) {
        $settings = $this->getSitemapSettings($variant);
        $errors[] = $this->validateIndexedArguments($settings['arguments']);
      }
    }

    return array_merge([], ...$errors);
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    if ($this->hasSitemapSettings()) {
      $categories['simple_sitemap'] = [
        'title' => $this->t('Simple XML Sitemap'),
        'column' => 'second',
      ];

      $included_variants = [];
      foreach ($this->sitemaps as $variant => $sitemap) {
        $settings = $this->getSitemapSettings($variant);

        if ($settings['index']) {
          $included_variants[] = $variant;
        }
      }

      $options['simple_sitemap'] = [
        'title' => NULL,
        'category' => 'simple_sitemap',
        'value' => $included_variants ? $this->t('Included in sitemaps: @variants', [
          '@variants' => implode(', ', $included_variants),
        ]) : $this->t('Excluded from all sitemaps'),
      ];
    }
  }

  /**
   * Gets the sitemap settings.
   *
   * @param string $variant
   *   The ID of the sitemap.
   *
   * @return array
   *   The sitemap settings.
   */
  public function getSitemapSettings(string $variant): array {
    $settings = [
      'index' => 0,
      'priority' => 0.5,
      'changefreq' => '',
      'arguments' => [],
      'max_links' => 100,
    ];

    if (isset($this->options['variants'][$variant])) {
      $settings = $this->options['variants'][$variant] + $settings;
    }

    if (empty($this->displayHandler->getHandlers('argument'))) {
      $settings['arguments'] = [];
    }
    else {
      $required_arguments = $this->getRequiredArguments();
      $settings['arguments'] = array_merge($settings['arguments'], $required_arguments);
    }

    return $settings;
  }

  /**
   * Identify whether or not the current display has sitemap settings.
   *
   * @return bool
   *   Has sitemap settings (TRUE) or not (FALSE).
   */
  public function hasSitemapSettings(): bool {
    return $this->displayHandler instanceof DisplayRouterInterface && !empty($this->sitemaps);
  }

  /**
   * Gets required view arguments (presented in the path).
   *
   * @return array
   *   View arguments IDs.
   */
  public function getRequiredArguments(): array {
    $arguments = $this->displayHandler->getHandlers('argument');

    if (!empty($arguments)) {
      $bits = explode('/', $this->displayHandler->getPath());
      $arg_counter = 0;

      foreach ($bits as $bit) {
        if ($bit === '%' || str_starts_with($bit, '%')) {
          $arg_counter++;
        }
      }

      if ($arg_counter > 0) {
        $arguments = array_slice(array_keys($arguments), 0, $arg_counter);
        return array_combine($arguments, $arguments);
      }
    }

    return [];
  }

  /**
   * Determines if the view path contains required arguments.
   *
   * @return bool
   *   TRUE if the path contains required arguments, FALSE if not.
   */
  public function hasRequiredArguments(): bool {
    $bits = explode('/', $this->displayHandler->getPath());

    foreach ($bits as $bit) {
      if ($bit === '%' || str_starts_with($bit, '%')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns available view arguments options.
   *
   * @return array
   *   View arguments labels keyed by argument ID.
   */
  protected function getArgumentsOptions(): array {
    $arguments = $this->displayHandler->getHandlers('argument');
    $arguments_options = [];

    /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
    foreach ($arguments as $id => $argument) {
      $arguments_options[$id] = $argument->adminLabel();
    }

    return $arguments_options;
  }

  /**
   * Validate indexed arguments.
   *
   * @param array $indexed_arguments
   *   Indexed arguments array.
   *
   * @return array
   *   An array of error strings. This will be empty if there are no validation
   *   errors.
   */
  protected function validateIndexedArguments(array $indexed_arguments): array {
    $arguments = $this->displayHandler->getHandlers('argument');
    $arguments = array_fill_keys(array_keys($arguments), 0);
    $arguments = array_merge($arguments, $indexed_arguments);
    reset($arguments);

    $errors = [];
    while (($argument = current($arguments)) !== FALSE) {
      $next_argument = next($arguments);
      if (empty($argument) && !empty($next_argument)) {
        $errors[] = $this->t('To enable indexing of an argument, you must enable indexing of all previous arguments.');
        break;
      }
    }

    return $errors;
  }

}
