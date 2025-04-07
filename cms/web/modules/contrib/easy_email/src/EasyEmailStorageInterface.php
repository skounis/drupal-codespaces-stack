<?php

namespace Drupal\easy_email;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\easy_email\Entity\EasyEmailInterface;

/**
 * Defines the storage handler class for Email entities.
 *
 * This extends the base storage class, adding required special handling for
 * Email entities.
 *
 * @ingroup easy_email
 */
interface EasyEmailStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the Entity Email Type storage
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   */
  public function getEmailTypeStorage();

  /**
   * Gets a list of Email revision IDs for a specific Email.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $entity
   *   The Email entity.
   *
   * @return int[]
   *   Email revision IDs (in ascending order).
   */
  public function revisionIds(EasyEmailInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Email author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Email revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $entity
   *   The Email entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(EasyEmailInterface $entity);

  /**
   * Unsets the language for all Email with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
