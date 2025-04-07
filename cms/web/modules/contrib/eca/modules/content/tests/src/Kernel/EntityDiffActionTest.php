<?php

namespace Drupal\Tests\eca_content\Kernel;

/**
 * Kernel tests for the entity diff action plugin.
 *
 * @group eca
 * @group eca_content
 */
class EntityDiffActionTest extends EntityDiffTestBase {

  /**
   * Tests the result of non-equal entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityNonEqualResult() {
    $config = $this->getConfig();
    $config['return_values'] = 1;

    $node = self::createAndGetNode('A test', 'This is a test.');
    $nodeToCompare = self::createAndGetNode('A test', 'This is a test.');
    self::addNodeAsToken($nodeToCompare);

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $action = $action_manager->createInstance('eca_diff_entity', $config);
    $action->execute($node);
    $result = self::getTokenValue('[result]');
    $this->assertTrue(str_contains($result, 'nid'));
    $this->assertTrue(str_contains($result, 'uuid'));
    $this->assertTrue(str_contains($result, 'vid'));
  }

  /**
   * Tests the result of equal entities with exclude list.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityEqualResult() {
    $config = $this->getConfig([], ['nid', 'uuid', 'vid']);
    $config['return_values'] = 1;

    $node = self::createAndGetNode('A first test', 'This is a test.');
    $nodeToCompare = self::createAndGetNode('A first test', 'This is a test.');
    self::addNodeAsToken($nodeToCompare);

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $action = $action_manager->createInstance('eca_diff_entity', $config);
    $action->execute($node);
    $this->assertEquals('', self::getTokenValue('[result]'));
  }

}
