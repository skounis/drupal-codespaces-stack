<?php

declare(strict_types=1);

namespace Drupal\dashboard\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\EntityPermissionsForm;

/**
 * Provides the permissions administration for dashboards.
 *
 * @todo This could be provided by core for config entities if
 * https://www.drupal.org/project/drupal/issues/3492584 lands.
 * We need to workaround for now.
 */
class DashboardPermissionsForm extends EntityPermissionsForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $bundle_entity_type = NULL, $bundle = NULL): array {
    // We need to adapt the variable name because EntityPermissionsForm expects
    // the route parameter to be named 'bundle'. However, Config Translation and
    // other modules expects the parameter to be named like the entity type.
    $bundle = $this->getRouteMatch()->getParameter('dashboard');
    return parent::buildForm($form, $form_state, $bundle_entity_type, $bundle);
  }

}
