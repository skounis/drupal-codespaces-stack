<?php

namespace Drupal\eca_render\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a handler that adds rendered output coming from ECA.
 *
 * @ViewsField("eca_render")
 */
class EcaRender extends FieldPluginBase {

  use RedirectDestinationTrait;

  /**
   * The trigger event service.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    $instance->triggerEvent = $container->get('eca.trigger_event');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['name'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('Specify a name to identify this field from the ECA event <em>ECA Views field</em>.'),
      '#default_value' => $this->options['name'],
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $relationship_entities = $values->_relationship_entities;
    $entity = $values->_entity;
    $build = [];
    $event = $this->triggerEvent->dispatchFromPlugin('eca_render:views_field', $this, $build, $entity, $relationship_entities);
    if ($event instanceof RenderEventInterface) {
      $build = &$event->getRenderArray();
    }
    return $this->renderer->render($build);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

}
