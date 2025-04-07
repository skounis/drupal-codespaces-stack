<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the value is a valid auto-purge period when calling strtotime().
 */
#[Constraint(
  id: 'ValidAutoPurgePeriod',
  label: new TranslatableMarkup('Auto-purge period', [], ['context' => 'Validation'])
)]
class ValidAutoPurgePeriodConstraint extends SymfonyConstraint {

  /**
   * The error message.
   */
  public string $message = "The time period '@value' is not valid. Some valid values would be '1 month, 10 days', '15 days', '3 hours, 15 minutes'.";

}
