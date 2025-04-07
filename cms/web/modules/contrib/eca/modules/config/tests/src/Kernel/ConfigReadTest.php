<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_config_read" action plugin.
 *
 * @group eca
 * @group eca_config
 */
class ConfigReadTest extends Base {

  /**
   * Tests ConfigRead.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testConfigRead(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $config = \Drupal::configFactory()->getEditable('system.site');
    $config->set('page.front', '/node');
    $config->save();

    // Create an action that reads from system site config.
    $defaults = [];
    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'token_name' => 'my_config_value',
      'include_overridden' => TRUE,
    ] + $defaults);
    $this->assertFalse($action->access(NULL), 'User without permissions must not have access.');

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'node.type.article',
      'config_key' => 'name',
      'token_name' => 'my_config_value',
      'include_overridden' => TRUE,
    ] + $defaults);
    $this->assertFalse($action->access(NULL), 'User without permissions must not have access.');

    // Now switching to user with permissions.
    $account_switcher->switchTo(User::load(2));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'token_name' => 'my_config_value',
      'include_overridden' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $this->assertEquals('/node', $token_services->replace('[my_config_value]'));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'token_name' => 'my_config_value',
      'include_overridden' => FALSE,
    ] + $defaults);
    $action->execute();
    $this->assertEquals('/node', $token_services->replace('[my_config_value]'));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'node.type.article',
      'config_key' => 'name',
      'token_name' => 'my_config_value',
      'include_overridden' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $this->assertEquals('Article', $token_services->replace('[my_config_value]'));

    $action = $action_manager->createInstance('eca_config_read', [
      'config_name' => 'node.type.article',
      'config_key' => '',
      'token_name' => 'whole_config',
      'include_overridden' => TRUE,
    ] + $defaults);
    $action->execute();

    $node_type_array = array_filter(\Drupal::configFactory()->get('node.type.article')->get());
    $this->assertTrue(isset($node_type_array['uuid'], $node_type_array['langcode'], $node_type_array['status'], $node_type_array['type'], $node_type_array['new_revision']));
    $this->assertTrue($token_services->getTokenData('whole_config') instanceof DataTransferObject, "Ensure that the added config data was transformed into a DTO.");
    $this->assertEquals(Yaml::encode($node_type_array), $token_services->replace('[whole_config]'));

    $account_switcher->switchBack();
  }

}
