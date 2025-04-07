<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\Action\ActionInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Tests for config forms of ECA plugins.
 *
 * @group eca
 * @group eca_core
 */
class PluginConfigFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'views',
    'workflows',
    'content_moderation',
    'eca',
    'eca_base',
    'eca_cache',
    'eca_config',
    'eca_content',
    'eca_form',
    'eca_log',
    'eca_migrate',
    'eca_misc',
    'eca_queue',
    'eca_user',
    'eca_views',
    'eca_workflow',
  ];

  /**
   * The service name for a logger implementation that collects anything logged.
   *
   * @var string
   */
  protected static string $testLogServiceName = 'eca_test.logger';

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register(self::$testLogServiceName, BufferingLogger::class)
      ->addTag('logger');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('view');
    $this->installEntitySchema('workflow');
    $this->installConfig(static::$modules);

    // Prepare the logger for collecting ECA log messages.
    $this->container->get(self::$testLogServiceName)->cleanLogs();
  }

  /**
   * Tests configuration forms of plugins.
   */
  public function testPluginConfigForms(): void {
    /** @var \Drupal\eca\Service\Modellers $eventManager */
    $eventManager = \Drupal::service('eca.service.modeller');
    foreach ($eventManager->events() as $event) {
      $this->doExecute('event', $event->getPluginId(), $event);
    }

    /** @var \Drupal\eca\Service\Conditions $conditionManager */
    $conditionManager = \Drupal::service('eca.service.condition');
    foreach ($conditionManager->conditions() as $condition) {
      $this->doExecute('condition', $condition->getPluginId(), $condition);
    }

    /** @var \Drupal\eca\Service\Actions $actionManager */
    $actionManager = \Drupal::service('eca.service.action');
    foreach ($actionManager->actions() as $action) {
      // Check that it's an ECA action plugin, we don't want to test core
      // plugins.
      if ($action instanceof ActionInterface) {
        $this->doExecute('action', $action->getPluginId(), $action);
      }
    }
  }

  /**
   * Execute all the config form assertions for a given plugin.
   *
   * @param string $type
   *   The plugin type, either event, condition or action.
   * @param string $id
   *   The plugin id.
   * @param mixed $plugin
   *   The plugin.
   */
  private function doExecute(string $type, string $id, mixed $plugin): void {
    if ($plugin instanceof PluginFormInterface) {
      $form_state = new FormState();

      $form = $plugin->buildConfigurationForm([], $form_state);
      $this->assertIsArray($form, 'The form for event ' . $id . ' should be an array.');
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Building the form for ' . $type . ' ' . $id . ' should not produce any errors.');

      $plugin->validateConfigurationForm($form, $form_state);
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Validating the form for ' . $type . ' ' . $id . ' should not produce any errors.');

      $plugin->submitConfigurationForm($form, $form_state);
      $log_messages = $this->container->get(self::$testLogServiceName)->cleanLogs();
      $this->assertEmpty($log_messages, 'Submitting the form for ' . $type . ' ' . $id . ' should not produce any errors.');
    }
  }

}
