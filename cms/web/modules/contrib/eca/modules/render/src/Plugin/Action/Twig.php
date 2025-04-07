<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup as RenderMarkup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Template\TwigEnvironment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Render an inline template using the Twig engine..
 *
 * @Action(
 *   id = "eca_render_twig",
 *   label = @Translation("Render: Twig"),
 *   description = @Translation("Render an inline template using the Twig engine."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Twig extends Markup {

  /**
   * The Twig environment service.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->twig = $container->get('twig');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'template' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Template'),
      '#description' => $this->t('Must be valid Twig syntax.'),
      '#weight' => -200,
      '#default_value' => $this->configuration['template'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['value']['#title'] = $this->t('Context values');
    $form['value']['#description'] = $this->t('Optionally specify context values to pass to the template. Can be an array using YAML syntax (needs to be enabled below) or a token holding the context data. Available token data will be automatically forwarded.');
    $form['value']['#required'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    try {
      $this->twig->renderInline($this->configuration['template']);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('template', $this->t('The provided template is not valid Twig.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['template'] = $form_state->getValue('template');
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $template = trim((string) $this->tokenService->replaceClear($this->configuration['template']));
    if ($template === '') {
      throw new \InvalidArgumentException("No template given for rendering an inline Twig template.");
    }
    $value = $this->configuration['value'];

    if ($this->configuration['use_yaml']) {
      try {
        $value = $this->yamlParser->parse($value);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a state value item in action "eca_render_twig" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $value = $this->tokenService->getOrReplace($value);
    }

    if ($value instanceof EntityInterface) {
      $type = $this->tokenService->getTokenTypeForEntityType($value->getEntityTypeId()) ?? $value->getEntityTypeId();
      $value = [$type => $value];
    }
    elseif (!is_iterable($value)) {
      $value = ['value' => $value];
    }

    $context = $this->tokenService->getTokenData();
    foreach ($value as $k => $v) {
      $context[$k] = $v;
    }

    $build = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => $context,
    ];

    // The built up context array may change its data values later on.
    // Therefore, apply the rendering right now. This also makes the rendered
    // result directly available in the token (if specified).
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });
    $metadata = BubbleableMetadata::createFromRenderArray($build);
    $build = ['#markup' => RenderMarkup::create($markup)];
    $metadata->applyTo($build);
  }

}
