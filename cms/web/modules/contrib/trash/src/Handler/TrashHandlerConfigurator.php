<?php

declare(strict_types=1);

namespace Drupal\trash\Handler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\trash\TrashManagerInterface;

/**
 * Provides a configurator for trash handler services.
 */
class TrashHandlerConfigurator {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * Configures a trash handler service.
   *
   * @param \Drupal\trash\Handler\TrashHandlerInterface $trashHandler
   *   A trash handler service.
   */
  public function __invoke(TrashHandlerInterface $trashHandler): void {
    $trashHandler->setEntityTypeManager($this->entityTypeManager);
    $trashHandler->setTrashManager($this->trashManager);
  }

}
