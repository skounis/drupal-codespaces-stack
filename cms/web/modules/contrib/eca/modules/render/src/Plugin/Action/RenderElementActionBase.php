<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Base class for actions that build up a render element.
 */
abstract class RenderElementActionBase extends RenderActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (trim(($this->configuration['token_name'] ?? '')) !== '') {
      // Access is allowed when a token name is specified.
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }
    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $build = [];
    $this->doBuild($build);
    if ($this->configuration['weight'] !== '') {
      $weight = trim((string) $this->tokenService->replaceClear($this->configuration['weight']));
      if ($weight !== '' && is_numeric($weight)) {
        $build['#weight'] = $weight;
      }
    }

    $token_name = trim((string) ($this->configuration['token_name'] ?? ''));
    if ($token_name !== '') {
      if (isset($build['#markup']) && empty(Element::children($build))) {
        $this->tokenService->addTokenData($token_name, $build['#markup']);
      }
      elseif (isset($build['#serialized'])) {
        $method = $build['#method'] ?? 'serialize';
        $this->tokenService->addTokenData($token_name, $method === 'serialize' ? $build['#serialized'] : $build['#data']);
      }
      else {
        $this->tokenService->addTokenData($token_name, $build);
      }
    }

    $event = $this->event;
    if (!($event instanceof RenderEventInterface)) {
      return;
    }
    $target = &$event->getRenderArray();

    // Collect cache metadata, and add some sensible defaults.
    $metadata = BubbleableMetadata::createFromRenderArray($target)
      ->merge(BubbleableMetadata::createFromRenderArray($build))
      ->addCacheContexts([
        'url.path',
        'url.query_args',
        'user',
        'user.permissions',
      ])
      ->addCacheTags(['config:eca_list']);

    $name = trim((string) $this->tokenService->replaceClear($this->configuration['name']));
    if ($name !== '') {
      $name = $this->getElementNameAsArray($name);
    }

    $mode = $this->configuration['mode'];
    if ($mode === '_eca_token') {
      $mode = $this->getTokenValue('mode', 'append');
    }
    switch ($mode) {

      case 'set:clear:defined':
        if ($name === '') {
          break;
        }

      case 'set:clear':
        if ($name === '') {
          $target = $build;
        }
        else {
          NestedArray::setValue($target, $name, $build, TRUE);
        }
        break;

      case 'merge':
        if ($name === '') {
          $target = NestedArray::mergeDeep($target, $build);
        }
        elseif (!NestedArray::keyExists($target, $name)) {
          NestedArray::setValue($target, $name, $build, TRUE);
        }
        else {
          NestedArray::setValue($target, $name, NestedArray::mergeDeep(NestedArray::getValue($target, $name), $build));
        }
        break;

      case 'prepend':
        $build['#weight'] = (count($target) + 100) * -1;
        if ($name === '') {
          $target = array_merge([$build], $target);
        }
        else {
          NestedArray::unsetValue($target, $name);
          $nested = [];
          NestedArray::setValue($nested, $name, $build, TRUE);
          $target = NestedArray::mergeDeep($nested, $target);
        }
        break;

      case 'append':
        $build['#weight'] = (count($target) + 100);
        if ($name === '') {
          $target = array_merge($target, [$build]);
        }
        else {
          NestedArray::unsetValue($target, $name);
          $nested = [];
          NestedArray::setValue($nested, $name, $build, TRUE);
          $target = NestedArray::mergeDeep($target, $nested);
        }
        break;

      case 'nothing':
        // This mode does nothing with the current build.
        break;

    }

    // Make sure to not lose any cache metadata.
    $metadata->applyTo($target);
  }

  /**
   * Inner logic for building up the render array.
   *
   * @param array &$build
   *   The render array.
   */
  abstract protected function doBuild(array &$build): void;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'token_name' => '',
      'weight' => '',
      'mode' => 'append',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Machine name'),
      '#description' => $this->t('Optionally define a machine name of this render element. It will be made available under that name in the render array of the current event in scope. Nested elements can be set with using "][" brackets, for example <em>details][title</em>.'),
      '#default_value' => $this->configuration['name'],
      '#required' => FALSE,
      '#weight' => -30,
    ];
    $form['token_name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Token name'),
      '#description' => $this->t('Optionally define a token name of this render element. It will be made available under that token name for later usage.'),
      '#default_value' => $this->configuration['token_name'],
      '#required' => FALSE,
      '#weight' => -28,
      '#eca_token_reference' => TRUE,
    ];
    $form['weight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Element weight'),
      '#description' => $this->t('Optionally specify an element weight. The lower the weight, the element appears before other elements having a higher weight.'),
      '#default_value' => $this->configuration['weight'],
      '#weight' => -25,
      '#required' => FALSE,
    ];
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Build mode'),
      '#options' => [
        'set:clear' => $this->t('Set and clear any previous value'),
        'set:clear:defined' => $this->t('Set value when machine name is defined above'),
        'merge' => $this->t('Merge with existing values'),
        'prepend' => $this->t('Prepend to existing values'),
        'append' => $this->t('Append to existing values'),
        'nothing' => $this->t('Do nothing'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['weight'] = $form_state->getValue('weight');
    $this->configuration['mode'] = $form_state->getValue('mode');
    parent::submitConfigurationForm($form, $form_state);
  }

}
