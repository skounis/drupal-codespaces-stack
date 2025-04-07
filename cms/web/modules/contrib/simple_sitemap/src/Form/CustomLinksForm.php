<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\simple_sitemap\Settings;

/**
 * Provides form to manage custom links.
 */
class CustomLinksForm extends SimpleSitemapFormBase {

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * CustomLinksForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\simple_sitemap\Manager\Generator $generator
   *   The sitemap generator service.
   * @param \Drupal\simple_sitemap\Settings $settings
   *   The simple_sitemap.settings service.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Helper class for working with forms.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    Generator $generator,
    Settings $settings,
    FormHelper $form_helper,
    PathValidatorInterface $path_validator,
  ) {
    parent::__construct(
      $config_factory,
      $typedConfigManager,
      $generator,
      $settings,
      $form_helper
    );
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_custom_links_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['variants']['#tree'] = TRUE;

    $custom_link_manager = $this->generator->customLinkManager();
    foreach ($custom_link_manager->getSitemaps() as $variant => $sitemap) {
      $custom_link_settings = $custom_link_manager->setSitemaps($sitemap)->get();

      $count = $custom_link_settings ? count($custom_link_settings[$variant]) : 0;
      $form['variants'][$sitemap->id()] = [
        '#type' => 'details',
        '#title' => $sitemap->label() . ($count ? ' (' . $count . ')' : ''),
        '#open' => (bool) $custom_link_settings,
      ];

      $form['variants'][$variant]['custom_links'] = [
        '#type' => 'textarea',
        '#default_value' => $custom_link_settings ? $this->customLinksToString($custom_link_settings[$variant]) : '',
      ];
    }

    $form['include_images'] = [
      '#type' => 'select',
      '#title' => $this->t('Include images'),
      '#description' => $this->t('If a custom link points to an entity, include its referenced images in the sitemap.'),
      '#default_value' => $this->settings->get('custom_links_include_images', FALSE),
      '#options' => [0 => $this->t('No'), 1 => $this->t('Yes')],
    ];

    $form = $this->formHelper->regenerateNowForm($form);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $sitemaps = $this->generator->customLinkManager()->setSitemaps()->getSitemaps();
    foreach ($form_state->getValue('variants') as $variant => $values) {
      foreach ($this->stringToCustomLinks($values['custom_links']) as $i => $link_config) {
        $placeholders = [
          '@sitemap' => $sitemaps[$variant]->label(),
          '@line' => ++$i,
          '@path' => $link_config['path'],
          '@priority' => $link_config['priority'] ?? '',
          '@changefreq' => $link_config['changefreq'] ?? '',
          '@changefreq_options' => implode(', ', array_keys(FormHelper::getChangefreqOptions())),
        ];

        // Checking if internal path exists.
        if (!$this->pathValidator->getUrlIfValidWithoutAccessCheck($link_config['path'])
          // Path validator does not see a double slash as an error. Catching
          // this to prevent breaking path generation.
          || str_contains($link_config['path'], '//')) {
          $form_state->setError($form['variants'][$variant]['custom_links'], $this->t('<strong>@sitemap, line @line</strong>: The path <em>@path</em> does not exist.', $placeholders));
        }

        // Making sure the paths start with a slash.
        if ($link_config['path'][0] !== '/') {
          $form_state->setError($form['variants'][$variant]['custom_links'], $this->t("<strong>@sitemap, line @line</strong>: The path <em>@path</em> needs to start with a '/'.", $placeholders));
        }

        // Making sure the priority is formatted correctly.
        if (isset($link_config['priority']) && !FormHelper::isValidPriority($link_config['priority'])) {
          $form_state->setError($form['variants'][$variant]['custom_links'], $this->t('<strong>@sitemap, line @line</strong>: The priority setting <em>@priority</em> for path <em>@path</em> is incorrect. Set the priority from 0.0 to 1.0.', $placeholders));
        }

        // Making sure changefreq is formatted correctly.
        if (isset($link_config['changefreq']) && !FormHelper::isValidChangefreq($link_config['changefreq'])) {
          $form_state->setError($form['variants'][$variant]['custom_links'], $this->t('<strong>@sitemap, line @line</strong>: The changefreq setting <em>@changefreq</em> for path <em>@path</em> is incorrect. The following are the correct values: <em>@changefreq_options</em>.', $placeholders));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $custom_link_manager = $this->generator->customLinkManager();
    foreach ($form_state->getValue('variants') as $variant => $values) {
      $custom_link_manager->setSitemaps($variant)->remove();
      foreach ($this->stringToCustomLinks($values['custom_links']) as $link_config) {
        $this->generator->customLinkManager()->add($link_config['path'], $link_config);
      }
    }
    $this->settings->save('custom_links_include_images', (bool) $form_state->getValue('include_images'));

    parent::submitForm($form, $form_state);
  }

  /**
   * Converts a string with custom links to an array.
   *
   * @param string $custom_links_string
   *   A string representation of the custom links to convert.
   *
   * @return array
   *   Array of custom links.
   */
  protected function stringToCustomLinks(string $custom_links_string): array {

    // Unify newline characters and explode into array.
    $custom_links_string_lines = explode("\n", str_replace("\r\n", "\n", $custom_links_string));

    // Remove empty values and whitespaces from array.
    $custom_links_string_lines = array_filter(array_map('trim', $custom_links_string_lines));

    $custom_links = [];
    foreach ($custom_links_string_lines as $i => $line) {
      $link_settings = explode(' ', $line);
      $custom_links[$i]['path'] = $link_settings[0];

      // If two arguments are provided for a link, assume the first to be
      // priority, the second to be changefreq.
      if (!empty($link_settings[1]) && !empty($link_settings[2])) {
        $custom_links[$i]['priority'] = $link_settings[1];
        $custom_links[$i]['changefreq'] = $link_settings[2];
      }
      else {
        // If one argument is provided for a link, guess if it is priority or
        // changefreq.
        if (!empty($link_settings[1])) {
          if (is_numeric($link_settings[1])) {
            $custom_links[$i]['priority'] = $link_settings[1];
          }
          else {
            $custom_links[$i]['changefreq'] = $link_settings[1];
          }
        }
      }
    }

    return $custom_links;
  }

  /**
   * Converts an array of custom links to a string.
   *
   * @param array $links
   *   Array of custom links to convert.
   *
   * @return string
   *   A string representation of the custom links.
   */
  protected function customLinksToString(array $links): string {
    $setting_string = '';
    foreach ($links as $custom_link) {
      $setting_string .= $custom_link['path'];
      $setting_string .= isset($custom_link['priority'])
        ? ' ' . FormHelper::formatPriority($custom_link['priority'])
        : '';
      $setting_string .= isset($custom_link['changefreq'])
        ? ' ' . $custom_link['changefreq']
        : '';
      $setting_string .= "\r\n";
    }

    return $setting_string;
  }

}
