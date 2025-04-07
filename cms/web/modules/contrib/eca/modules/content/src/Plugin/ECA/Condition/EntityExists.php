<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca_content\Service\EntityLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ECA condition for entity exists.
 *
 * @EcaCondition(
 *   id = "eca_entity_exists",
 *   label = @Translation("Entity: exists"),
 *   description = @Translation("Performs a lookup whether an entity exists and is accessible."),
 *   eca_version_introduced = "1.0.0",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityExists extends ConditionBase {

  /**
   * The entity loader.
   *
   * @var \Drupal\eca_content\Service\EntityLoader|null
   */
  protected ?EntityLoader $entityLoader;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setEntityLoader($container->get('eca_content.service.entity_loader'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if ($entity = $this->entityLoader()->loadEntity($this->getValueFromContext('entity'), $this->configuration, 'eca_entity_exists')) {
      return $this->negationCheck($entity->access('view'));
    }
    return $this->negationCheck(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return $this->entityLoader()->defaultConfiguration() + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = $this->entityLoader()->buildConfigurationForm($this->configuration, $form, $form_state);
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->entityLoader()->validateConfigurationForm($this->configuration, $form, $form_state);
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->entityLoader()->submitConfigurationForm($this->configuration, $form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Get the entity loader.
   *
   * @return \Drupal\eca_content\Service\EntityLoader
   *   The entity loader.
   */
  public function entityLoader(): EntityLoader {
    if (!isset($this->entityLoader)) {
      // @phpstan-ignore-next-line
      $this->entityLoader = \Drupal::service('eca_content.service.entity_loader');
    }
    return $this->entityLoader;
  }

  /**
   * Set the entity loader.
   *
   * @param \Drupal\eca_content\Service\EntityLoader $entity_loader
   *   The entity loader.
   */
  public function setEntityLoader(EntityLoader $entity_loader): void {
    $this->entityLoader = $entity_loader;
  }

}
