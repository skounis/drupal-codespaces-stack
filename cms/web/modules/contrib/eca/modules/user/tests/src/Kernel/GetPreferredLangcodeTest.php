<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Kernel tests for the "eca_get_preferred_langcode" action plugin.
 *
 * @group eca
 * @group eca_user
 */
class GetPreferredLangcodeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_user',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'preferred_langcode' => 'en',
    ])->save();
  }

  /**
   * Tests GetPreferredLangcode.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testGetPreferredLangcode(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    /** @var \Drupal\eca_user\Plugin\Action\LoadCurrentUser $action */
    $action = $action_manager->createInstance('eca_get_preferred_langcode', ['token_name' => 'langcode']);
    $action->execute(User::load(1));
    $this->assertEquals('en', $token_services->replace('[langcode]'));
  }

}
