<?php

namespace Drupal\easy_email_override\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\easy_email\Entity\EasyEmailTypeInterface;

class EntityHooks {

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @param EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  #[Hook('easy_email_type_delete')]
  public function easyEmailTypeDelete(EasyEmailTypeInterface $easy_email_type) {
    $storage = $this->entityTypeManager->getStorage('easy_email_override');
    $overrides = $storage->loadByProperties(['easy_email_type' => $easy_email_type->id()]);
    $storage->delete($overrides);
  }


}
