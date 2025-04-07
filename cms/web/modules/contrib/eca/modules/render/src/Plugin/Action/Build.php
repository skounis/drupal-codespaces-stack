<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Build a custom defined render array.
 *
 * @Action(
 *   id = "eca_render_build",
 *   label = @Translation("Render: build"),
 *   description = @Translation("Build a custom defined render array."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Build extends RenderElementActionBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value' => '',
      'use_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => $this->t('Value'),
      '#description' => $this->t('The value of the render build. This can be arbitrary markup text or a valid <a href=":url" target="_blank" rel="nofollow noreferrer">render array</a>.', [
        ':url' => 'https://www.drupal.org/docs/drupal-apis/render-api/render-arrays',
      ]),
      '#default_value' => $this->configuration['value'],
      '#weight' => -20,
      '#eca_token_replacement' => TRUE,
    ];
    if (isset($this->configuration['use_yaml'])) {
      $form['use_yaml'] = [
        '#type' => 'checkbox',
        '#required' => FALSE,
        '#title' => $this->t('Interpret above value as YAML format'),
        '#description' => $this->t('Nested data can be set using YAML format, for example <em>mykey: "My value"</em>. When using this format, this option needs to be enabled. When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
        '#default_value' => $this->configuration['use_yaml'],
        '#weight' => -10,
      ];
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['value'] = $form_state->getValue('value');
    if (isset($this->configuration['use_yaml'])) {
      $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $value = $this->configuration['value'];

    if (!empty($this->configuration['use_yaml'])) {
      try {
        $value = $this->yamlParser->parse($value);
      }
      catch (ParseException) {
        $this->logger->error('Tried parsing a state value item in action "eca_render_build" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $value = $this->tokenService->getOrReplace($value);
    }

    $this->doBuildRecursive($build, $value);
  }

  /**
   * Recursively builds up the render array.
   *
   * @param array &$build
   *   The render array to build.
   * @param mixed &$value
   *   The value to use for building up the given render array.
   */
  protected function doBuildRecursive(array &$build, mixed &$value): void {
    $weight = count($build);
    $wrap_as_list = $this->wrapAsList($value);

    if ($value instanceof DataTransferObject) {
      $dto_array = $value->toArray();
      $is_render_array = FALSE;
      array_walk_recursive($dto_array, static function ($v, $k) use (&$is_render_array) {
        if (in_array($k, ['#type', '#theme'])) {
          $is_render_array = TRUE;
        }
      });
      if ($is_render_array) {
        $build = $dto_array;
        return;
      }
    }

    if (is_array($value)) {
      $is_render_array = FALSE;
      array_walk_recursive($value, static function ($v, $k) use (&$is_render_array) {
        if (in_array($k, ['#type', '#theme'])) {
          $is_render_array = TRUE;
        }
      });
      if ($is_render_array) {
        $build = $value;
        return;
      }
    }

    if (!is_iterable($value)) {
      $value = [$value];
    }

    foreach ($value as $k => $v) {
      if ($v instanceof DataTransferObject) {
        $v = $v->getValue();
        if (is_string($v) || (is_object($v) && method_exists($v, '__toString'))) {
          $build[$k] = [
            '#type' => 'container',
            '#attributes' => [
              'id' => Html::getUniqueId('eca-dto-' . $k),
              'class' => [
                'eca-dto',
                Html::getClass('dto-container-' . $k),
              ],
            ],
            '#weight' => ($weight += 10),
          ];
          $build[$k][] = [
            '#type' => 'markup',
            '#markup' => Markup::create($v),
            '#weight' => -10000,
          ];
        }
        elseif (isset($v['values'])) {
          $wrap_as_list = FALSE;
          if (isset($v['_string_representation'])) {
            $build[$k] = [
              '#type' => 'details',
              '#open' => TRUE,
              '#title' => Markup::create($v['_string_representation']),
              '#attributes' => [
                'id' => Html::getUniqueId('eca-dto-' . $k),
                'class' => [
                  'eca-dto',
                  Html::getClass('dto-details-' . $k),
                ],
              ],
              '#weight' => ($weight += 10),
            ];
          }
          else {
            $build[$k] = [
              '#type' => 'container',
              '#attributes' => [
                'id' => Html::getUniqueId('eca-dto-' . $k),
                'class' => [
                  'eca-dto',
                  Html::getClass('dto-container-' . $k),
                ],
              ],
              '#weight' => ($weight += 10),
            ];
          }
          $this->doBuildRecursive($build[$k], $v['values']);
        }
      }
      elseif ($v instanceof EntityAdapter) {
        $v = $v->getValue();
      }
      elseif ($v instanceof TypedDataInterface) {
        $v = $v->getString();
      }
      if ($v instanceof EntityInterface) {
        $build[$k] = $v->hasLinkTemplate('canonical') ? $v->toLink($v->label(), 'canonical')->toRenderable() : ['#markup' => $v->label()];
      }
      elseif (is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))) {
        $build[$k] = [
          '#type' => 'markup',
          '#markup' => $v,
        ];
      }
    }

    if ($wrap_as_list) {
      $children = [];
      foreach (Element::children($build) as $key) {
        $children[$key] = $build[$key];
        unset($build[$key]);
      }
      if (isset($build['#type'])) {
        $build[] = [
          '#theme' => 'item_list',
          '#items' => $children,
        ];
      }
      else {
        $build = [
          '#theme' => 'item_list',
          '#items' => $children,
        ];
      }
    }

  }

  /**
   * Whether to wrap the given value as HTML list.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   Returns TRUE if it should be wrapped, FALSE otherwise.
   */
  protected function wrapAsList(mixed $value): bool {
    if (is_iterable($value)) {
      foreach ($value as $k => $v) {
        if (!is_int($k)) {
          return FALSE;
        }
        if (!is_scalar($v) && !(is_object($v) && (method_exists($v, '__toString') || $v instanceof EntityInterface))) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

}
