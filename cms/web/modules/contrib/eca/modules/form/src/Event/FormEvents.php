<?php

namespace Drupal\eca_form\Event;

/**
 * Events dispatched by the eca_form module.
 */
final class FormEvents {

  /**
   * Dispatches on form building.
   *
   * @Event
   *
   * @var string
   */
  public const BUILD = 'eca.form.build';

  /**
   * Dispatches on form processing.
   *
   * @Event
   *
   * @var string
   */
  public const PROCESS = 'eca.form.process';

  /**
   * Dispatches on after form building.
   *
   * @Event
   *
   * @var string
   */
  public const AFTER_BUILD = 'eca.form.after_build';

  /**
   * Dispatches on form validation.
   *
   * @Event
   *
   * @var string
   */
  public const VALIDATE = 'eca.form.validate';

  /**
   * Dispatches on form submission.
   *
   * @Event
   *
   * @var string
   */
  public const SUBMIT = 'eca.form.submit';

  /**
   * Dispatches when an inline entity form is being build.
   *
   * @Event
   *
   * @var string
   */
  public const IEF_BUILD = 'eca.form.ief_build';

}
