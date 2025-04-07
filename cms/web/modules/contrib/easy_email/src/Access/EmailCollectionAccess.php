<?php

namespace Drupal\easy_email\Access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
* Checks access for displaying the email log
*/
class EmailCollectionAccess implements AccessInterface {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  public function access(AccountInterface $account) {
    $easy_email_settings = $this->configFactory->get('easy_email.settings');
    $collection_access = !empty($easy_email_settings->get('email_collection_access'));
    return AccessResult::allowedIf($collection_access)->addCacheTags(['config:easy_email.settings']);
  }

}
