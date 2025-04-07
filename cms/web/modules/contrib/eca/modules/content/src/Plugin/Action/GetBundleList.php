<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get a list of bundles.
 *
 * @Action(
 *   id = "eca_get_bundle_list",
 *   label = @Translation("Entity: get list of bundles"),
 *   description = @Translation("Gets the list of bundles for a given entity type."),
 *   eca_version_introduced = "2.1.0"
 * )
 */
class GetBundleList extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The entity type service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypes = $container->get('eca.service.content_entity_types');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
      'type' => '',
      'mode' => 'ids',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('Provide the name of a token that holds the bundle list.'),
      '#weight' => -60,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $options = $this->entityTypes->getTypes();
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $options,
      '#default_value' => $this->configuration['type'],
      '#description' => $this->t('The entity type for which to receive the list of bundles.'),
      '#weight' => -50,
      '#eca_token_select_option' => TRUE,
    ];
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'ids' => $this->t('IDs'),
        'labels' => $this->t('Labels'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#description' => $this->t('This either returns a list of bundle IDs or of their labels.'),
      '#weight' => -40,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['type'] = $form_state->getValue('type');
    $this->configuration['mode'] = $form_state->getValue('mode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if ($this->configuration['type'] === '_eca_token') {
      $type = $this->getTokenValue('type', '');
    }
    else {
      $type = $this->configuration['type'];
    }
    $bundles = $this->entityTypes->getBundles($type);
    if ($this->configuration['mode'] === 'ids') {
      $bundles = array_keys($bundles);
    }
    $this->tokenService->addTokenData($this->configuration['token_name'], $bundles);
  }

}
