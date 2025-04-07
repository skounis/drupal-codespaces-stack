<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Validation\Constraint;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a given string can be used as a period in the past.
 */
class ValidAutoPurgePeriodConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (empty($value)) {
      return;
    }

    assert($constraint instanceof ValidAutoPurgePeriodConstraint);
    $timestamp = strtotime(sprintf("-%s", $value));
    if (!$timestamp || $timestamp >= $this->time->getCurrentTime()) {
      $this->context->addViolation($constraint->message, ['@value' => $value]);
    }
  }

}
