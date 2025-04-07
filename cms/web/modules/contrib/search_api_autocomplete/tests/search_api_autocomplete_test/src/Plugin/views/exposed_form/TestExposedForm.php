<?php

namespace Drupal\search_api_autocomplete_test\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\views\Plugin\views\exposed_form\Basic;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposed form plugin that provides a basic exposed form.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "search_api_autocomplete_test",
 *   title = @Translation("Search API Autocomplete Test Exposed Form"),
 *   help = @Translation("Provides an exposed form plugin to simulate the one provided by the ""better_exposed_filters"" module."),
 * )
 */
class TestExposedForm extends Basic {

  /**
   * The element info manager.
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->elementInfo = $container->get('plugin.manager.element_info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    // Ensure default process/pre_render callbacks are included.
    // Copied from BetterExposedFilters::exposedFormAlter().
    // See comments in ::addDefaultElementInfo().
    foreach (Element::children($form) as $key) {
      $element = &$form[$key];
      $this->addDefaultElementInfo($element);
    }
  }

  /**
   * Adds default element callbacks.
   *
   * Copied from BetterExposedFilters::addDefaultElementInfo().
   *
   * This is a workaround to a problem where adding process or pre-render
   * callbacks to a form element results in replacing the default ones instead
   * of merging.
   *
   * @param array $element
   *   The render array for a single form element.
   *
   * @see \Drupal\better_exposed_filters\Plugin\views\exposed_form\BetterExposedFilters::addDefaultElementInfo()
   */
  protected function addDefaultElementInfo(array &$element): void {
    /** @var \Drupal\Core\Render\ElementInfoManager $element_info_manager */
    $element_info = $this->elementInfo;
    if (isset($element['#type']) && empty($element['#defaults_loaded']) && ($info = $element_info->getInfo($element['#type']))) {
      $element['#process'] = $element['#process'] ?? [];
      $element['#pre_render'] = $element['#pre_render'] ?? [];
      if (!empty($info['#process'])) {
        $element['#process'] = array_merge($info['#process'], $element['#process']);
      }
      if (!empty($info['#pre_render'])) {
        $element['#pre_render'] = array_merge($info['#pre_render'], $element['#pre_render']);
      }

      // Some processing needs to happen prior to the default form element
      // callbacks (e.g. sort). We use the custom '#pre_process' array for this.
      if (!empty($element['#pre_process'])) {
        $element['#process'] = array_merge($element['#pre_process'], $element['#process']);
      }

      // Workaround to add support for #group FAPI to all elements currently not
      // supported.
      if (!in_array('processGroup', array_column($element['#process'], 1))) {
        $element['#process'][] = ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'];
        $element['#pre_render'][] = ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'];
      }
    }

    // Apply the same to any nested children.
    foreach (Element::children($element) as $key) {
      $child = &$element[$key];
      $this->addDefaultElementInfo($child);
    }
  }

}
