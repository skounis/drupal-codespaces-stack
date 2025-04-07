<?php

namespace Drupal\eca_content\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * The ECA Content validation constraint.
 */
#[Constraint(
  id: 'EcaContent',
  label: new TranslatableMarkup('ECA Content validation', [], ['context' => 'Validation'])
)]
class EcaConstraint extends SymfonyConstraint {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\eca_content\Plugin\Validation\Constraint\EcaConstraintValidator';
  }

}
