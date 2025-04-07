<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Service\EntityLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Load an entity into the token environment.
 *
 * @Action(
 *   id = "eca_token_load_entity",
 *   label = @Translation("Entity: load"),
 *   description = @Translation("Load a single entity from current scope or by certain properties, and store it as a token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class LoadEntity extends ConfigurableActionBase {

  /**
   * The entity loader.
   *
   * @var \Drupal\eca_content\Service\EntityLoader|null
   */
  protected ?EntityLoader $entityLoader;

  /**
   * The loaded entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity;

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
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = AccessResult::forbidden();
    $reason = NULL;
    if ($entity = $this->doLoadEntity($object)) {
      $access_result = $entity->access('view', $account, TRUE);
      if (!$access_result->isAllowed()) {
        $reason = 'No permission to view the entity.';
      }
    }
    else {
      $reason = 'No entity available.';
    }
    if ($reason !== NULL && $access_result instanceof AccessResultReasonInterface) {
      $access_result->setReason($reason);
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $entity = $this->doLoadEntity($entity);

    $token = $this->tokenService;
    $config = &$this->configuration;
    $tokenName = isset($config['token_name']) ? trim($config['token_name']) : '';
    if (($tokenName === '') && $entity) {
      $tokenName = (string) $token->getTokenType($entity);
    }
    if ($tokenName !== '') {
      $token->addTokenData($tokenName, $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['token_name' => '']
      + $this->entityLoader()->defaultConfiguration()
      + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('Provide the name of a token that holds the loaded entity.'),
      '#weight' => -90,
      '#eca_token_reference' => TRUE,
    ];
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
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->entityLoader()->submitConfigurationForm($this->configuration, $form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Loads the entity by using the currently given plugin configuration.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   (Optional) A passed through entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded entity, or NULL if not found.
   *
   * @throws \InvalidArgumentException
   *   When the provided argument is not NULL and not an entity object.
   */
  protected function doLoadEntity(?EntityInterface $entity = NULL): ?EntityInterface {
    $this->entity = $this->entityLoader()->loadEntity($entity, $this->configuration);
    return $this->entity ?? NULL;
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
