<?php

namespace Drupal\eca_project_browser\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_project_browser\Event\ProjectBrowserSourceInfoAlterEvent;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the eca_project_browser_source_plugin_info_alter action.
 *
 * @Action(
 *   id = "eca_project_browser_source_plugin_info_alter",
 *   label = @Translation("Project Browser: Alter source plugin info"),
 *   description = @Translation("Allows to change certain properties of source plugins."),
 *   eca_version_introduced = "2.1.2"
 * )
 */
class AlterSourcePluginInfo extends ConfigurableActionBase {

  /**
   * The project browser source manager.
   *
   * @var \Drupal\project_browser\Plugin\ProjectBrowserSourceManager
   */
  protected ProjectBrowserSourceManager $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->manager = $container->get(ProjectBrowserSourceManager::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $access_result = AccessResult::forbidden();
    $event = $this->getEvent();
    if ($event instanceof ProjectBrowserSourceInfoAlterEvent) {
      if ($event->pluginExists($this->configuration['plugin_id'])) {
        $access_result = AccessResult::allowed();
      }
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    /** @var \Drupal\eca_project_browser\Event\ProjectBrowserSourceInfoAlterEvent $event */
    $event = $this->getEvent();
    foreach ([
      'label' => FALSE,
      'description' => FALSE,
      'title' => TRUE,
      'weight' => TRUE,
    ] as $key => $localTask) {
      $configKey = $localTask ? 'local_task_' . $key : $key;
      if ($this->configuration[$configKey] !== '') {
        $event->setProperty($this->configuration['plugin_id'], $key, $this->configuration[$configKey], $localTask);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'plugin_id' => '',
      'label' => '',
      'description' => '',
      'local_task_title' => '',
      'local_task_weight' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->manager->getDefinitions() as $definition) {
      $options[$definition['id']] = $definition['label'];
    }
    $form['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Plugin'),
      '#default_value' => $this->configuration['plugin_id'],
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->configuration['label'],
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description'],
    ];
    $form['local_task_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local task: title'),
      '#default_value' => $this->configuration['local_task_title'],
    ];
    $form['local_task_weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Local task: weight'),
      '#default_value' => $this->configuration['local_task_weight'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['plugin_id'] = $form_state->getValue('plugin_id');
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['local_task_title'] = $form_state->getValue('local_task_title');
    $this->configuration['local_task_weight'] = $form_state->getValue('local_task_weight');
    parent::submitConfigurationForm($form, $form_state);
  }

}
