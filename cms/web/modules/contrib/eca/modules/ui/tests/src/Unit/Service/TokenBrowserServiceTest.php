<?php

namespace Drupal\Tests\eca_ui\Unit\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\eca\Unit\EcaUnitTestBase;
use Drupal\eca_ui\Service\TokenBrowserService;

/**
 * Tests the token browser service.
 *
 * @group eca
 * @group eca_core
 */
class TokenBrowserServiceTest extends EcaUnitTestBase {

  /**
   * Tests the token browser markup if contrib token module is not installed.
   */
  public function testTokenModuleNotInstalled(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $tokenBrowserService = new TokenBrowserService($moduleHandler);
    $this->assertEquals([], $tokenBrowserService->getTokenBrowserMarkup());
  }

  /**
   * Tests the method getTokenBrowserMarkup.
   */
  public function testGetMarkup(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->once())->method('moduleExists')
      ->with('token')->willReturn(TRUE);

    $tokenBrowserService = new TokenBrowserService($moduleHandler);

    $markup = [
      'tb' => [
        '#type' => 'container',
        '#theme' => 'token_tree_link',
        '#token_types' => 'all',
      ],
    ];
    $this->assertEquals($markup, $tokenBrowserService->getTokenBrowserMarkup());
  }

}
