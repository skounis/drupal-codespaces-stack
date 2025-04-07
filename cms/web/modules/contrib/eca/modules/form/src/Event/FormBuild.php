<?php

namespace Drupal\eca_form\Event;

use Drupal\eca\Event\RenderEventInterface;

/**
 * Dispatched when a form is being build.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_form\Event
 */
class FormBuild extends FormBase implements RenderEventInterface {

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    $form = &$this->getForm();
    return $form;
  }

}
