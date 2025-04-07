<?php

namespace Drupal\sitemap\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Url;
use Drupal\sitemap\SitemapManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration form for sitemap.
 */
class SitemapSettingsForm extends ConfigFormBase {

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected RequestContext $requestContext;

  /**
   * Service to build and manage the router table.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected RouteBuilderInterface $routeBuilder;

  /**
   * The SitemapMap plugin manager.
   *
   * @var \Drupal\sitemap\SitemapManager
   */
  protected SitemapManager $sitemapManager;

  /**
   * An array of Sitemap plugins.
   *
   * @var \Drupal\sitemap\SitemapInterface[]
   */
  protected array $plugins = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('config.factory'),
      $container->get('config.typed')
    );

    $form->requestContext = $container->get('router.request_context');
    $form->routeBuilder = $container->get('router.builder');
    $form->sitemapManager = $container->get('plugin.manager.sitemap');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitemap_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('sitemap.settings');

    $form['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#default_value' => $config->get('page_title'),
      '#description' => $this->t('Page title that will be used on the @sitemap_page.', ['@sitemap_page' => Link::fromTextAndUrl($this->t('sitemap page'), Url::fromRoute('sitemap.page'))->toString()]),
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#default_value' => $config->get('path'),
      '#size' => 40,
      '#description' => $this->t('Optionally, specify a relative URL to display the sitemap on. Defaults to <code>/sitemap</code>.'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
      '#element_validate' => ['::isPathValid'],
    ];

    $sitemap_message = $config->get('message');
    $form['message'] = [
      '#type' => 'text_format',
      '#format' => $sitemap_message['format'] ?? NULL,
      '#title' => $this->t('Sitemap message'),
      '#default_value' => $sitemap_message['value'],
      '#description' => $this->t('Define a message to be displayed above the sitemap.'),
    ];

    // Retrieve stored configuration for the plugins.
    $plugins = $config->get('plugins');

    // Create plugin instances for all available Sitemap plugins, including both
    // enabled/configured ones as well as new and not yet configured ones.
    $definitions = $this->sitemapManager->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if ($this->sitemapManager->hasDefinition($id)) {
        $plugin_config = [];
        if (!empty($plugins[$id])) {
          $plugin_config = $plugins[$id];
        }
        $this->plugins[$id] = $this->sitemapManager->createInstance($id, $plugin_config);
      }
    }

    // Plugin status.
    $form['plugins']['enabled'] = [
      '#type' => 'item',
      '#title' => $this->t('Enabled plugins'),
      '#prefix' => '<div id="sitemap-enabled-wrapper">',
      '#suffix' => '</div>',
      // This item is used as a pure wrapping container with heading. Ignore its
      // value, since 'plugins' should only contain plugin definitions.
      // See https://www.drupal.org/node/1829202.
      '#input' => FALSE,
    ];
    // Plugin order (tabledrag).
    $form['plugins']['order'] = [
      '#type' => 'table',
      // For sitemap.admin.js.
      '#attributes' => ['id' => 'sitemap-order'],
      '#title' => $this->t('Plugin display order'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'plugin-order-weight',
        ],
      ],
      '#tree' => FALSE,
      '#input' => FALSE,
      '#theme_wrappers' => ['form_element'],
    ];
    // Map settings.
    $form['plugin_settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Plugin settings'),
    ];

    $defaultSort = $this->plugins;
    $sorted = $this->sortPlugins($this->plugins);

    foreach ($sorted as $id => $plugin) {
      /** @var \Drupal\sitemap\SitemapBase $plugin */

      $form['plugins']['enabled'][$id] = [
        '#type' => 'checkbox',
        '#title' => $plugin->getLabel(),
        '#default_value' => $plugin->enabled,
        '#parents' => ['plugins', $id, 'enabled'],
        '#description' => $plugin->getDescription(),
        // Default sort groups by plugin type.
        '#weight' => $defaultSort[$id]->weight,
      ];

      $form['plugins']['order'][$id]['#attributes']['class'][] = 'draggable';
      $form['plugins']['order'][$id]['#weight'] = $plugin->weight;
      $form['plugins']['order'][$id]['filter'] = [
        '#markup' => $plugin->getLabel(),
      ];
      $form['plugins']['order'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $plugin->getLabel()]),
        '#title_display' => 'invisible',
        '#delta' => 50,
        '#default_value' => $plugin->weight,
        '#parents' => ['plugins', $id, 'weight'],
        '#attributes' => ['class' => ['plugin-order-weight']],
      ];

      // Retrieve the settings form of the Sitemap plugin.
      $settings_form = [
        '#parents' => ['plugins', $id, 'settings'],
        '#tree' => TRUE,
      ];
      $settings_form = $plugin->settingsForm($settings_form, $form_state);
      if (!empty($settings_form)) {
        $form['plugins']['settings'][$id] = [
          '#type' => 'details',
          '#title' => $plugin->getLabel(),
          '#open' => TRUE,
          '#weight' => $plugin->weight,
          '#parents' => ['plugins', $id, 'settings'],
          '#group' => 'plugin_settings',
        ];
        $form['plugins']['settings'][$id] += $settings_form;
      }
    }
    $form['#attached']['library'][] = 'sitemap/sitemap.admin';

    // Sitemap CSS settings.
    $form['css'] = [
      '#type' => 'details',
      '#title' => $this->t('CSS settings'),
    ];
    $form['css']['include_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include sitemap CSS file'),
      '#default_value' => $config->get('include_css'),
      '#description' => $this->t("Select this box if you wish to load the CSS file included with the module. To learn how to override or specify the CSS at the theme level, visit the @documentation_page.", ['@documentation_page' => Link::fromTextAndUrl($this->t("documentation page"), Url::fromUri('https://www.drupal.org/node/2615568'))->toString()]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('sitemap.settings');

    // If the path changed, tell the router that a rebuild is needed.
    if ($config->get('path') !== $form_state->getValue('path')) {
      $this->routeBuilder->setRebuildNeeded();
    }

    // Save config.
    foreach ($form_state->cleanValues()->getValues() as $key => $value) {
      if ($key == 'plugins') {
        $settings = [];
        foreach ($value as $instance_id => $plugin_config) {
          $plugin = $this->plugins[$instance_id];

          // Don't save settings of disabled plugins.
          if (empty($plugin_config['enabled'])) {
            // Save the fact that the plugin is disabled for the ones enabled
            // by default.
            if (!empty($plugin->getPluginDefinition()['enabled'])) {
              $settings[$instance_id] = [
                'enabled' => FALSE,
              ];
            }

            continue;
          }

          // Update the plugin configurations.
          $plugin->setConfiguration($plugin_config);
          $settings[$instance_id] = $plugin->getConfiguration();
        }
        // Save in sitemap.settings.
        $config->set($key, $settings);
      }
      else {
        $config->set($key, $value);
      }
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sitemap.settings'];
  }

  /**
   * Sort the plugins by weight.
   *
   * @param array $plugins
   *   The plugins to be sorted.
   *
   * @return array
   *   Returns the new plugins order.
   */
  protected function sortPlugins(array $plugins) {
    // We cannot use array_column here because pluginId is protected.
    // $order = array_column($plugins, 'weight', 'publicId');.
    $order = [];
    foreach ($plugins as $id => $plugin) {
      $order[$id] = $plugin->weight;
    }
    asort($order);
    foreach ($order as $id => $weight) {
      $order[$id] = $plugins[$id];
    }

    return $order;
  }

  /**
   * Form element validation handler for a path.
   *
   * @param array &$element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $completeForm
   *   The complete form structure.
   *
   * @see \Drupal\Core\Path\PathValidator::getUrl()
   */
  public static function isPathValid(array &$element, FormStateInterface $formState, array $completeForm) {
    $path = trim($element['#value']);

    // We can't control external URLs, so display an error if one is entered.
    if (UrlHelper::isExternal($path)) {
      $formState->setError($element, t('External paths are not allowed.'));
      return;
    }

    // If left empty, set to default, "sitemap".
    if (empty($path)) {
      $path = '/sitemap';
      $formState->setValueForElement($element, $path);
    }
    // If the user didn't add a slash to the beginning so it is stored
    // consistently.
    elseif ($path[0] !== '/') {
      $formState->setValueForElement($element, '/' . $path);
    }

    // Parse the path into its components (path, query, fragment). Note that
    // while we want to *store* the path with a leading slash (see above),
    // UrlHelper prefers that we remove it before parsing.
    $parsedUrl = UrlHelper::parse(ltrim($path, '/'));

    // Symfony's router cannot make routes available at query components.
    if (!empty($parsedUrl['query'])) {
      $formState->setError($element, t('URL query components like %query are not allowed.', [
        '%query' => implode('&', $parsedUrl['query']),
      ]));
    }

    // Symfony's router cannot make routes available at fragments.
    if (!empty($parsedUrl['fragment'])) {
      $formState->setError($element, t('URL fragment components like %fragment are not allowed.', [
        '%fragment' => $parsedUrl['fragment'],
      ]));
    }

    // We cannot make the sitemap available at <front>, i.e.: /; rather, <front>
    // needs to be set to the sitemap path.
    if ($parsedUrl['path'] === '<front>' || $parsedUrl['path'] === '/') {
      $formState->setError($element, t('Cannot set the path of the sitemap to the front page.'));
    }
    // We cannot make the sitemap available at <current>, which refers to the
    // current path, whatever it may be.
    elseif ($parsedUrl['path'] === '<current>') {
      $formState->setError($element, t('Cannot set the path of the sitemap to the current path.'));
    }
    // The paths <none>, <nolink>, and <button> have special meanings in certain
    // contexts; don't allow them.
    elseif ($parsedUrl['path'] === '<none>'
      || $parsedUrl['path'] === '<nolink>'
      || $parsedUrl['path'] === '<button>'
    ) {
      $formState->setError($element, t('Cannot set the path of the sitemap to the reserved path %path.'));
    }
  }

}
