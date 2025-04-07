<?php

namespace Drupal\project_browser\Plugin\Block;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\project_browser\EnabledSourceHandler;
use Drupal\project_browser\Plugin\Derivative\BlockDeriver;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;
use Drupal\project_browser\ProjectBrowser\Filter\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that displays a project browser.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[Block(
  id: 'project_browser_block',
  category: new TranslatableMarkup('Project Browser'),
  deriver: BlockDeriver::class,
)]
final class ProjectBrowserBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The source plugin to query for projects.
   *
   * @var \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
   */
  private readonly ProjectBrowserSourceInterface $source;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EnabledSourceHandler $enabledSources,
    private readonly ElementInfoManagerInterface $elementInfo,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $source_id = $this->getDerivativeId();
    $this->source = $enabledSources->getCurrentSources()[$source_id];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EnabledSourceHandler::class),
      $container->get(ElementInfoManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'paginate' => TRUE,
      // Use the default page sizes defined by the render element.
      'page_sizes' => implode(', ', $this->elementInfo->getInfoProperty('project_browser', '#page_sizes')),
      // Allow all sort options defined by the source plugin.
      'sort_options' => NULL,
      // Use the default sort criterion chosen by the render element.
      'default_sort' => NULL,
      // Allow all filters defined by the source plugin.
      'filters' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer modules');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $configuration = $this->getConfiguration();
    $form['paginate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pagination'),
      '#default_value' => $configuration['paginate'],
    ];
    $form['page_sizes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page sizes'),
      '#required' => TRUE,
      '#default_value' => $configuration['page_sizes'],
      '#description' => $this->t('A comma-separated list of choices for how many projects to show per page. Can also be a single number, to only ever show that many projects.'),
    ];
    $sort_options = $this->source->getSortOptions();
    $form['sort_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Sort options'),
      '#required' => TRUE,
      '#options' => $sort_options,
      '#default_value' => $configuration['sort_options'] ?? array_keys($sort_options),
    ];
    $form['default_sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Default sort'),
      '#required' => TRUE,
      '#default_value' => $configuration['default_sort'] ?? array_key_first($sort_options),
      '#options' => $sort_options,
    ];
    $filter_definitions = $this->source->getFilterDefinitions();
    $form['filters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled filters'),
      '#options' => array_map(
        fn (FilterBase $filter): string => $filter->name,
        $filter_definitions,
      ),
      '#default_value' => $configuration['filters'] ?? array_keys($filter_definitions),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    parent::blockValidate($form, $form_state);

    $valid = Inspector::assertAll(
      fn (string $value): bool => is_numeric($value) && intval($value) > 0,
      static::pageSizesToArray($form_state->getValue('page_sizes')),
    );
    if (!$valid) {
      $form_state->setError(
        $form['page_sizes'],
        $this->t('The page sizes must be a comma-separated list of numbers greater than zero.'),
      );
    }

    if (!in_array($form_state->getValue('default_sort'), $form_state->getValue('sort_options'), TRUE)) {
      $form_state->setError(
        $form['default_sort'],
        $this->t('The default sort must be one of the enabled sort options.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['paginate'] = (bool) $form_state->getValue('paginate');
    $this->configuration['page_sizes'] = trim($form_state->getValue('page_sizes'));
    $this->configuration['sort_options'] = array_values(array_filter($form_state->getValue('sort_options')));
    $this->configuration['default_sort'] = $form_state->getValue('default_sort');
    $this->configuration['filters'] = array_values(array_filter($form_state->getValue('filters')));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $source = $this->getDerivativeId();
    assert(is_string($source));

    $configuration = $this->getConfiguration();
    // Allow preview mode to be simulated in tests.
    if (drupal_valid_test_ua() && array_key_exists('simulate_preview', $configuration)) {
      $this->inPreview = $configuration['simulate_preview'];
    }

    // We don't want to actually load the project browser in preview mode.
    if ($this->inPreview) {
      return [
        '#markup' => $this->t('Project Browser is being rendered in preview mode, so not loading projects. This block uses the %source source.', [
          '%source' => $this->source->getPluginDefinition()['label'],
        ]),
        // The preview isn't cacheable.
        '#cache' => ['max-age' => 0],
      ];
    }

    if (isset($configuration['sort_options'])) {
      // Only show the sort options that are allowed by our configuration.
      $sort_options = array_intersect_key(
        $this->source->getSortOptions(),
        array_flip($configuration['sort_options']),
      );
    }
    if (isset($configuration['filters'])) {
      // Only show the filters that are allowed by our configuration.
      $filters = array_intersect_key(
        $this->source->getFilterDefinitions(),
        array_flip($configuration['filters']),
      );
      // The render element's #filters property expects an associative array
      // whose keys are filter names and whose values are the values for those
      // filters. This block doesn't currently offer any way to change the
      // filters' values from their defaults.
      $filters = array_map(
        fn (FilterBase $filter): mixed => $filter->getValue(),
        $filters,
      );
    }

    return [
      '#type' => 'project_browser',
      '#source' => $this->source,
      '#cache' => [
        'tags' => [
          $this->getBaseId(),
        ],
      ],
      '#paginate' => $configuration['paginate'],
      '#page_sizes' => static::pageSizesToArray($configuration['page_sizes']),
      // If #sort_options is NULL, the sort options defined by the source are
      // used as-is.
      '#sort_options' => $sort_options ?? NULL,
      '#sort_by' => $configuration['default_sort'],
      // If #filters is NULL, the filters defined by the source are used as-is.
      '#filters' => $filters ?? NULL,
    ];
  }

  /**
   * Converts the `page_sizes` configuration option to an array.
   *
   * @param string $page_sizes
   *   A comma-separated list of numbers.
   *
   * @return string[]
   *   The configured page sizes.
   */
  private static function pageSizesToArray(string $page_sizes): array {
    return array_map('trim', explode(',', $page_sizes));
  }

}
