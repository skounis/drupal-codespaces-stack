<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_config_write" action plugin.
 *
 * @group eca
 * @group eca_config
 */
class ConfigWriteTest extends Base {

  /**
   * Tests ConfigWrite.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testConfigWrite(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $config = \Drupal::configFactory()->getEditable('system.site');
    $config->set('page.front', '/node');
    $config->save();

    // Create an action that reads from system site config.
    $defaults = [];
    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'config_value' => '/eca-frontpage',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertFalse($action->access(NULL), 'User without permissions must not have access.');

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'node.type.article',
      'config_key' => 'name',
      'config_value' => 'ECA Article',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertFalse($action->access(NULL), 'User without permissions must not have access.');

    // Now switching to user with permissions.
    $account_switcher->switchTo(User::load(2));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'config_value' => '/eca-frontpage',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $this->assertEquals('/eca-frontpage', \Drupal::configFactory()->get('system.site')->get('page.front'));

    $config_value = <<<YAML
403: ''
404: ''
front: /another-frontpage
YAML;
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'system.site',
      'config_key' => 'page',
      'config_value' => $config_value,
      'use_yaml' => TRUE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $this->assertEquals('/another-frontpage', \Drupal::configFactory()->get('system.site')->get('page.front'));

    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'node.type.article',
      'config_key' => 'name',
      'config_value' => 'ECA Article',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $this->assertEquals('ECA Article', \Drupal::configFactory()->get('node.type.article')->get('name'));

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('[myconfigvalue]', 'Set via token');
    /** @var \Drupal\eca_config\Plugin\Action\ConfigRead $action */
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'node.type.article',
      'config_key' => 'name',
      'config_value' => '[myconfigvalue]',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ] + $defaults);
    $action->execute();

    $this->assertEquals('Set via token', \Drupal::configFactory()->get('node.type.article')->get('name'));

    $yaml = <<<YAML
uuid: a1921798-6c7f-4772-bb37-d3d46386fba9
langcode: en
status: true
dependencies: {  }
name: Updated Article
type: article
description: null
help: null
new_revision: true
preview_mode: 1
display_submitted: true
YAML;
    $action = $action_manager->createInstance('eca_config_write', [
      'config_name' => 'node.type.article',
      'config_key' => '',
      'config_value' => $yaml,
      'use_yaml' => TRUE,
      'save_config' => TRUE,
    ] + $defaults);
    $this->assertTrue($action->access(NULL), 'User with permissions must have access.');
    $action->execute();

    $node_type_array = \Drupal::configFactory()->get('node.type.article')->get();
    $this->assertEquals('a1921798-6c7f-4772-bb37-d3d46386fba9', $node_type_array['uuid']);
    $this->assertEquals('en', $node_type_array['langcode']);
    $this->assertEquals('Updated Article', $node_type_array['name']);
    $this->assertEquals(Yaml::decode($yaml), $node_type_array);

    $account_switcher->switchBack();
  }

}
