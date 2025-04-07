<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

/**
 * Tests entity access for trashed entities.
 *
 * @group trash
 */
class EntityAccessTest extends TrashKernelTestBase {

  /**
   * Tests entity access for trashed entities.
   *
   * @dataProvider providerTestEntityAccess
   */
  public function testEntityAccess(string $permission, array $access_map): void {
    $account = $this->createUser([
      'access content',
      $permission,
    ]);

    $node = $this->createNode(['type' => 'article', 'uid' => $account->id()]);
    $node->delete();

    foreach ($access_map as $operation => $access_result) {
      $this->assertSame($access_result, $node->access($operation, $account));
    }
  }

  /**
   * Data provider for self::testConstructor()
   */
  public static function providerTestEntityAccess() {
    return [
      [
        'bypass node access',
        [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => FALSE,
          'purge' => FALSE,
        ],
      ],
      [
        'edit any article content',
        [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => FALSE,
          'purge' => FALSE,
        ],
      ],
      [
        'delete any article content',
        [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => FALSE,
          'purge' => FALSE,
        ],
      ],
      [
        'view deleted entities',
        [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => FALSE,
          'purge' => FALSE,
        ],
      ],
      [
        'restore node entities',
        [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => TRUE,
          'purge' => FALSE,
        ],
      ],
      [
        'purge node entities',
        [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
          'restore' => FALSE,
          'purge' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Tests entity access for entity types that are not enabled.
   */
  public function testEntityAccessForNotDeletedEntity(): void {
    $account = $this->createUser([
      'administer users',
    ]);

    $this->assertTrue($account->access('view', $account));
    $this->assertFalse($account->access('restore', $account));
    $this->assertFalse($account->access('purge', $account));
  }

}
