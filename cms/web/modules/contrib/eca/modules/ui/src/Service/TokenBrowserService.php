<?php

namespace Drupal\eca_ui\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service class for Token Browser in ECA.
 */
class TokenBrowserService {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs the token browser service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Returns the markup needed for the token browser.
   *
   * @return array
   *   Markup for the token browser.
   */
  public function getTokenBrowserMarkup(): array {
    if (!$this->moduleHandler->moduleExists('token')) {
      return [];
    }

    return [
      'tb' => [
        '#type' => 'container',
        '#theme' => 'token_tree_link',
        '#token_types' => 'all',
      ],
    ];
  }

}
