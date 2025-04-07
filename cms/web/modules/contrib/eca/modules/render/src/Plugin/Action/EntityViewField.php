<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Service\YamlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * View a field of a specified entity.
 *
 * @Action(
 *   id = "eca_render_entity_view_field",
 *   label = @Translation("Render: view field"),
 *   description = @Translation("View a field of a specified entity."),
 *   eca_version_introduced = "1.1.0",
 *   type = "entity"
 * )
 */
class EntityViewField extends RenderElementActionBase {

  /**
   * The current entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity = NULL;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setYamlParser($container->get('eca.service.yaml_parser'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'view_mode' => 'default',
      'display_options' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('Field name'),
      '#default_value' => $this->configuration['field_name'],
      '#description' => $this->t('The machine name of the field. Example: <em>field_tags</em>'),
      '#required' => TRUE,
      '#weight' => -40,
    ];
    $form['view_mode'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'alwaysFalse'],
      ],
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
      '#description' => $this->t('Example: <em>default, teaser</em>'),
      '#required' => FALSE,
      '#weight' => -30,
    ];
    $form['display_options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Display options'),
      '#description' => $this->t('Alternatively, instead of specifying a view mode above, you can set custom display options here by using YAML format.'),
      '#default_value' => $this->configuration['display_options'],
      '#required' => FALSE,
      '#weight' => -20,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->configuration['display_options'] = $form_state->getValue('display_options');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access_result = parent::access($object, $account, TRUE);
    $name = $this->tokenService->replace($this->configuration['field_name']);
    if ($access_result->isAllowed()) {
      if (!($object instanceof FieldableEntityInterface)) {
        $access_result = AccessResult::forbidden("The given object is not a fieldable entity.");
      }
      elseif (!$object->hasField($name)) {
        $access_result = AccessResult::forbidden(sprintf("The entity has no specified field %s", $name));
      }
      else {
        $account = $account ?? $this->currentUser;
        $access_result = $object
          ->access('view', $account, TRUE)
          ->andIf($object->{$name}->access('view', $account, TRUE));
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof EntityInterface)) {
      return;
    }
    $this->entity = $entity;
    parent::execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    if (!($this->entity instanceof FieldableEntityInterface)) {
      throw new \InvalidArgumentException("No fieldable entity given for building the entity field view.");
    }

    $name = $this->tokenService->replace($this->configuration['field_name']);
    $view_mode = trim((string) $this->tokenService->replaceClear($this->configuration['view_mode']));
    $display_options = $this->configuration['display_options'] ?? NULL;
    if ($display_options) {
      try {
        $display_options = $this->yamlParser->parse($display_options);
      }
      catch (ParseException $e) {
        $this->logger->error('Tried parsing a state value item in action "eca_render_entity_view_field" as YAML format, but parsing failed.');
        $build = [];
        return;
      }
    }

    if (($view_mode === '') && !$display_options) {
      throw new \InvalidArgumentException("No view mode or display settings specified.");
    }

    $build = $this->entityTypeManager
      ->getViewBuilder($this->entity->getEntityTypeId())
      ->viewField($this->entity->get($name), $display_options ?: $view_mode);
  }

  /**
   * Set the YAML parser.
   *
   * @param \Drupal\eca\Service\YamlParser $yaml_parser
   *   The YAML parser.
   */
  public function setYamlParser(YamlParser $yaml_parser): void {
    $this->yamlParser = $yaml_parser;
  }

}
