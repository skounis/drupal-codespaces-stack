<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup as RenderMarkup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\views\Entity\View;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Render the contents of a configured view.
 *
 * @Action(
 *   id = "eca_render_views",
 *   label = @Translation("Render: Views"),
 *   description = @Translation("Render the contents of a configured view."),
 *   eca_version_introduced = "1.1.0",
 *   deriver = "Drupal\eca_render\Plugin\Action\ViewsDeriver"
 * )
 */
class Views extends RenderElementActionBase {

  use PluginFormTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'view_id' => '',
      'display_id' => 'default',
      'arguments' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $views = [];
    foreach (View::loadMultiple() as $view) {
      if ($view->status()) {
        $views[$view->id()] = $view->label();
      }
    }
    $form['view_id'] = [
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#default_value' => $this->configuration['view_id'],
      '#weight' => -50,
      '#options' => $views,
      '#required' => TRUE,
      '#eca_token_select_option' => TRUE,
    ];
    $form['display_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display'),
      '#default_value' => $this->configuration['display_id'],
      '#weight' => -40,
      '#required' => FALSE,
    ];
    $form['arguments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Arguments'),
      '#default_value' => $this->configuration['arguments'],
      '#weight' => -30,
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['view_id'] = $form_state->getValue('view_id');
    $this->configuration['display_id'] = $form_state->getValue('display_id');
    $this->configuration['arguments'] = $form_state->getValue('arguments');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    $view_id = $this->getViewId();
    if ($view_id !== '') {
      $view = View::load($view_id);
      if ($view && $view->status()) {
        $viewExecutable = $view->getExecutable();
        $display_id = $this->getDisplayId();
        $display = NULL;
        if ($display_id !== '') {
          if ($viewExecutable->setDisplay($display_id)) {
            $display = $viewExecutable->getDisplay();
          }
        }
        else {
          $viewExecutable->initDisplay();
          $display = $viewExecutable->getDisplay();
        }
        if ($display !== NULL) {
          $result = AccessResult::allowedIf($display->access($account));
        }
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $args = [$this->getViewId(), $this->getDisplayId()];
    foreach (explode('/', $this->getArguments()) as $argument) {
      if ($argument !== '') {
        $args[] = $argument;
      }
    }

    $build = views_embed_view(...$args);

    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });
    $metadata = BubbleableMetadata::createFromRenderArray($build);
    $build = ['#markup' => RenderMarkup::create($markup)];
    $metadata->applyTo($build);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $view_id = $this->getViewId();
    if ($view_id !== '') {
      $dependencies['config'][] = 'views.view.' . $view_id;
    }
    return $dependencies;
  }

  /**
   * Get the configured view ID.
   *
   * @return string
   *   The view ID.
   */
  protected function getViewId(): string {
    $view_id = $this->configuration['view_id'];
    if ($view_id === '_eca_token') {
      $view_id = $this->getTokenValue('view_id', '');
    }
    return trim((string) $view_id);
  }

  /**
   * Get the configured display ID.
   *
   * @return string
   *   The display ID.
   */
  protected function getDisplayId(): string {
    return trim((string) $this->tokenService->replaceClear($this->configuration['display_id']));
  }

  /**
   * Get the configured Views arguments.
   *
   * @return string
   *   The arguments, multiple arguments are separated by "/".
   */
  protected function getArguments(): string {
    return trim((string) $this->tokenService->replaceClear($this->configuration['arguments']));
  }

}
