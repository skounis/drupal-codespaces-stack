<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get a list of entity types.
 *
 * @Action(
 *   id = "eca_get_entity_type_list",
 *   label = @Translation("Entity: get list of entity types"),
 *   description = @Translation("Gets the list of entity types."),
 *   eca_version_introduced = "2.1.0"
 * )
 */
class GetEntityTypeList extends ConfigurableActionBase {

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
      '#description' => $this->t('Provide the name of a token that holds the entity types list.'),
      '#weight' => -60,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options' => [
        'ids' => $this->t('IDs'),
        'labels' => $this->t('Labels'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#description' => $this->t('This either returns a list of entity type IDs or of their labels.'),
      '#weight' => -40,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['mode'] = $form_state->getValue('mode');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $types = $this->entityTypes->getTypes();
    if ($this->configuration['mode'] === 'ids') {
      $types = array_keys($types);
    }
    $this->tokenService->addTokenData($this->configuration['token_name'], $types);
  }

}
