<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Build a dropbutton element.
 *
 * @Action(
 *   id = "eca_render_dropbutton",
 *   label = @Translation("Render: dropbutton"),
 *   description = @Translation("Build a HTML dropbutton element."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Dropbutton extends RenderElementActionBase {

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
  protected function doBuild(array &$build): void {
    $links = $this->configuration['links'];

    if ($this->configuration['use_yaml']) {
      try {
        $links = $this->yamlParser->parse($links);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a state value item in action "eca_render_dropbutton" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $links = $this->tokenService->getOrReplace($links);
    }

    if ($links instanceof DataTransferObject) {
      $links = $links->toArray();
    }
    elseif ($links instanceof TypedDataInterface) {
      $links = $links->getValue();
    }
    if (!is_array($links)) {
      $links = [$links];
    }
    if (is_array($links)) {
      if (!isset($links[0])) {
        $links = [$links];
      }
      foreach ($links as $i => &$link) {
        if ($link instanceof EntityInterface) {
          $link = [
            'title' => $link->label(),
            'url' => $link->hasLinkTemplate('canonical') ? $link->toUrl('canonical') : NULL,
          ];
        }
        if (isset($link['#type'], $link['#title'], $link['#url'])) {
          $link = [
            'title' => $link['#title'],
            'url' => $link['#url'],
          ];
        }
        if (isset($link['url']) && is_string($link['url'])) {
          try {
            $link['url'] = Url::fromUserInput($link['url']);
          }
          catch (\Exception $e) {
            $link['url'] = Url::fromUri($link['url']);
          }
        }

        if (!isset($link['url']) || !($link['url'] instanceof Url)) {
          unset($links[$i]);
        }
      }
      unset($link);
    }

    $build = [
      '#type' => 'dropbutton',
      '#links' => $links,
    ];
    $dropbutton_type = trim($this->configuration['dropbutton_type'] ?? '');
    if ($dropbutton_type !== '') {
      $build['#dropbutton_type'] = $dropbutton_type;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'dropbutton_type' => 'small',
      'links' => '',
      'use_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['dropbutton_type'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Dropbutton type'),
      '#description' => $this->t('A string defining a type of dropbutton variant for styling proposes. Renders as class "dropbutton--[type]".'),
      '#weight' => -30,
      '#default_value' => $this->configuration['dropbutton_type'],
      '#required' => FALSE,
    ];
    $form['links'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Links'),
      '#description' => $this->t('This field optionally supports YAML if selected below.'),
      '#weight' => -20,
      '#default_value' => $this->configuration['links'],
      '#required' => FALSE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above specified links as YAML format'),
      '#description' => $this->t('Links can be specified as a list with YAML syntax. Example:<em><br/>-<br/>&nbsp;&nbsp;title: Edit<br/>&nbsp;&nbsp;url: "/node/[node:nid]/edit/"<br/>-<br>&nbsp;&nbsp;title: Delete<br/>&nbsp;&nbsp;url: "/node/[node:nid]/delete"</em><br/><br/>When using tokens and YAML altogether, make sure that tokens are wrapped as a string. Example: <em>title: "[node:title]"</em>'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -10,
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['dropbutton_type'] = $form_state->getValue('dropbutton_type');
    $this->configuration['links'] = $form_state->getValue('links');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
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
