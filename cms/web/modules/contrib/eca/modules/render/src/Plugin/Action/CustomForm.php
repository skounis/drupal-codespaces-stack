<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca_render\Form\EcaCustomForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build a custom form.
 *
 * @Action(
 *   id = "eca_render_custom_form",
 *   label = @Translation("Render: custom form"),
 *   description = @Translation("Build a custom form using ""ECA Form"" events."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class CustomForm extends RenderElementActionBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'custom_form_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['custom_form_id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Custom form ID'),
      '#description' => $this->t('This custom form ID is being used to identify the form on <em>ECA Form</em> events. <em>It is always prefixed with "eca_custom_"</em>. Example: When specified the custom form ID <em>my_custom_form</em>, then it can be identified e.g. on the event <em>Build form</em> using the form ID <em>eca_custom_my_custom_form</em>.'),
      '#default_value' => $this->configuration['custom_form_id'],
      '#required' => TRUE,
      '#weight' => 100,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['custom_form_id'] = $form_state->getValue('custom_form_id', 'eca_custom_form');
    if (mb_strpos($this->configuration['custom_form_id'], 'eca_custom_') !== 0) {
      $this->configuration['custom_form_id'] = 'eca_custom_' . $this->configuration['custom_form_id'];
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $form_id = $this->configuration['custom_form_id'] ?? '';
    if (trim($form_id) === '') {
      $form_id = 'eca_custom_form';
    }
    if (mb_strpos($form_id, 'eca_custom_') !== 0) {
      $form_id = 'eca_custom_' . $form_id;
    }
    $build = $this->formBuilder->getForm(new EcaCustomForm($form_id));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $form_id = $this->configuration['form_id'] ?? 'eca_custom_form';
    if (mb_strpos($form_id, 'eca_custom_') !== 0) {
      $form_id = 'eca_custom_' . $form_id;
    }
    $configs = [];
    /** @var \Drupal\eca\Entity\Eca $eca */
    foreach ($this->entityTypeManager->getStorage('eca')->loadMultiple() as $eca) {
      foreach (($eca->get('events') ?? []) as $event) {
        if (mb_strpos($event['plugin'], 'form:') !== 0) {
          continue;
        }
        $configured_form_id = $event['configuration']['form_id'] ?? '';
        if ($configured_form_id === $form_id) {
          $configs[$eca->id()] = $eca;
        }
      }
    }
    foreach ($configs as $config) {
      $dependencies[$config->getConfigDependencyKey()][] = $config->getConfigDependencyName();
    }
    return $dependencies;
  }

}
