<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\RenderEventInterface;

/**
 * Add a class to the attributes of an existing render array element.
 *
 * @Action(
 *   id = "eca_render_add_class",
 *   label = @Translation("Render: add class"),
 *   description = @Translation("Add a class to the attributes of an existing render array element. Only works when reacting upon a rendering event, such as <em>Build form</em> or <em>Build ECA Block</em>."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class AddClass extends RenderActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => '',
      'class' => '',
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
      '#description' => $this->t('Specify the machine name / key of the render element.'),
      '#default_value' => $this->configuration['name'],
      '#weight' => -30,
      '#required' => TRUE,
    ];
    $form['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class name'),
      '#description' => $this->t('The name of the class.'),
      '#default_value' => $this->configuration['class'],
      '#weight' => -25,
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['name'] = $form_state->getValue('name');
    $this->configuration['class'] = $form_state->getValue('class');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $event = $this->event;
    if (!($event instanceof RenderEventInterface)) {
      return;
    }

    $name = trim((string) $this->tokenService->replaceClear($this->configuration['name']));
    $class = trim((string) $this->tokenService->replaceClear($this->configuration['class']));
    $build = &$event->getRenderArray();
    $element = &$this->getTargetElement($name, $build);

    if ($element) {
      $element['#attributes']['class'][] = $class;
    }
  }

}
