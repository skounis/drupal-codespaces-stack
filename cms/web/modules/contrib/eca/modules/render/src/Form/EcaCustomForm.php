<?php

namespace Drupal\eca_render\Form;

use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form object for custom forms defined by ECA.
 */
class EcaCustomForm implements BaseFormIdInterface {

  /**
   * The custom form ID.
   *
   * @var string
   */
  protected string $formId;

  /**
   * Constructs a new EcaCustomForm object.
   *
   * @param string $form_id
   *   The custom form ID. It must be prefixed with "eca_custom_".
   */
  public function __construct(string $form_id) {
    if (!(mb_strpos($form_id, 'eca_custom_') === 0)) {
      throw new \InvalidArgumentException("The provided custom form ID must start with \"eca_custom_\". It cannot be used for other form IDs.");
    }
    $this->formId = $form_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId(): ?string {
    return 'eca_custom';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->formId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $submit_handlers = $form_state->getSubmitHandlers();
    $self_submit_handler = [$this, 'submitForm'];
    if (!in_array($self_submit_handler, $submit_handlers, TRUE)) {
      // Make sure the submit handler of this form is always included.
      $form_state->setSubmitHandlers(array_merge($submit_handlers, [$self_submit_handler]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getRedirect() || $form_state->isRedirectDisabled()) {
      // A custom form is often embedded on already existing pages.
      // When no redirect is specified, the server may respond with a 303
      // status code, which leads to execution of all submission logic on the
      // server side, but the result will never be displayed on the page. That
      // is caused by the 303 status code, instructing the browser to send
      // another request again to the same page. Enforcing a form rebuild here
      // prevents the server from responding with a 303, and directly responds
      // with a 200 status code.
      $form_state->setRebuild(TRUE);
    }
  }

}
