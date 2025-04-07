<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Un-serializes / deserializes data.
 *
 * @Action(
 *   id = "eca_render_unserialize",
 *   label = @Translation("Render: unserialize"),
 *   description = @Translation("Un-serializes / deserializes data."),
 *   eca_version_introduced = "1.1.0",
 *   deriver = "Drupal\eca_render\Plugin\Action\SerializeDeriver"
 * )
 */
class Unserialize extends Serialize {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $values = ['type' => 'array'] + parent::defaultConfiguration();
    unset($values['use_yaml']);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $data_type_options = [
      'array' => $this->t('Array'),
    ];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      $data_type_options[$entity_type->id()] = $entity_type->getLabel();
    }
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $data_type_options,
      '#default_value' => $this->configuration['type'],
      '#required' => TRUE,
      '#weight' => -200,
      '#eca_token_select_option' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['value']['#description'] = $this->t('The value to deserialize.');
    $form['value']['#eca_token_replacement'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['type'] = $form_state->getValue('type');
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $value = $this->configuration['value'];
    $serialized = (string) $this->tokenService->replace($value);

    $format = $this->configuration['format'];
    $type = $this->configuration['type'];
    if ($type === '_eca_token') {
      $type = $this->getTokenValue('type', 'array');
    }
    if ($type === 'array') {
      $data = $this->serializer->decode($serialized, $format);
    }
    elseif ($this->entityTypeManager->hasDefinition($type)) {
      $type = $this->entityTypeManager->getDefinition($type)->getClass();
      $data = $this->serializer->deserialize($serialized, $type, $format);
    }
    else {
      throw new \InvalidArgumentException(sprintf("The provided data type %s is not supported for being unserialized.", $type));
    }
    $build = [
      '#theme' => 'eca_serialized',
      '#method' => 'unserialize',
      '#serialized' => $serialized,
      '#format' => $format,
      '#data' => $data,
    ];
  }

}
