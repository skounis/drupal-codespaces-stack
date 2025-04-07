<?php

namespace Drupal\easy_email\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Utility\Token;
use Drupal\easy_email\Service\EasyEmailPurgerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
final class PurgeCommands extends DrushCommands {

  /**
   * Constructs an EasyEmailCommands object.
   */
  public function __construct(
    private readonly EasyEmailPurgerInterface $easyEmailPurger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('easy_email.purger'),
    );
  }

  /**
   * Purge easy emails sent before a certain timestamp.
   */
  #[CLI\Command(name: 'easy_email:purge_emails', aliases: ['email-purge'])]
  #[CLI\Option(name: 'types', description: 'Email templates')]
  #[CLI\Option(name: 'before_timestamp', description: 'Purge emails sent before timestamp')]
  #[CLI\Option(name: 'limit', description: 'Limit')]
  #[CLI\Usage(name: 'easy_email:purge_emails', description: 'Purge all emails based on their template configuration')]
  #[CLI\Usage(name: 'easy_email:purge_emails --types=template_a,template_b', description: 'Purge all emails of type template_a and template_b based on their template configuration')]
  #[CLI\Usage(name: 'easy_email:purge_emails --types=template_a,template_b --before_timestamp=1706215802', description: 'Purge all emails of type template_a and template_b sent before timestamp 1706215802')]
  #[CLI\Usage(name: 'easy_email:purge_emails --types=template_a,template_b --before_timestamp="-100 days"', description: 'Purge all emails of type template_a and template_b sent before 100 days ago')]
  #[CLI\Usage(name: 'easy_email:purge_emails --types=template_a,template_b --before_timestamp=1706215802 --limit=500', description: 'Purge up to 500 emails of type template_a and template_b sent before timestamp 1706215802')]
  public function purgeEmails($options = ['types' => NULL, 'before_timestamp' => NULL, 'limit' => NULL]) {
    $types = ($options['types'] === NULL) ? [] : explode(',', $options['types']);
    $before_timestamp = $options['before_timestamp'];
    if (!is_numeric($before_timestamp)) {
      $before_timestamp = strtotime($before_timestamp);
    }
    $limit = $options['limit'];

    $message = [];
    if (empty($types)) {
      $message[] = dt('Purging all email templates');
    }
    else {
      $message[] = dt('Purging email templates @templates', ['@templates' => implode(', ', $types)]);
    }
    if (empty($before_timestamp)) {
      $message[] = dt('based on their template configuration');
    }
    else {
      $message[] = dt('sent before timestamp @timestamp', ['@timestamp' => $before_timestamp]);
    }
    if (!empty($limit)) {
      $message[] = dt('with a limit of @limit emails', ['@limit' => $limit]);
    }
    $message = implode(' ', $message);
    $this->logger()->notice($message);

    $this->easyEmailPurger->purgeEmails($types, $before_timestamp, $limit);
    $this->logger()->success(dt('Email purge complete.'));
  }

}
