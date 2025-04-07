<?php

namespace Drupal\easy_email\Service;

interface EasyEmailPurgerInterface {

  public function purgeEmails(array $types = [], ?int $beforeTimestamp = NULL, ?int $limit = NULL);

}
