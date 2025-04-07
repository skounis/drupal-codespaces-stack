<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormPluginTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build an entity from submitted form input.
 *
 * @Action(
 *   id = "eca_form_build_entity",
 *   label = @Translation("Entity form: build entity"),
 *   description = @Translation("Build an entity from submitted form input and store the result as a token."),
 *   eca_version_introduced = "1.0.0",
 *   type = "form"
 * )
 */
class FormBuildEntity extends ConfigurableActionBase {

  use FormPluginTrait;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The built entity will be stored into this specified token. Please note: An entity can only be built when a form got submitted. Example events where it works: <em>Validate form</em>, <em>Submit form</em>.'),
      '#required' => TRUE,
      '#weight' => -45,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state ? $form_state->getFormObject() : NULL;
    $result = $this->getCurrentForm() && ($form_object instanceof EntityFormInterface) ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state ? $form_state->getFormObject() : NULL;
    $form = &$this->getCurrentForm();
    if (!$form || !($form_object instanceof EntityFormInterface)) {
      return;
    }

    $triggering_element = &$form_state->getTriggeringElement();

    // For incomplete submissions, simulate a complete form build. Only that
    // way we are able to receive normalized form input values, and then we
    // are finally able to build an entity from user input.
    $needs_manual_build = !$form_state->has('eca_skip_manual_build')
      && (!empty($form_state->getUserInput()) && (empty($form_state->getValues()) || !$form_state->isValidationComplete() || ($triggering_element && FALSE !== ($triggering_element['#limit_validation_errors'] ?? FALSE))));

    if ($needs_manual_build) {
      unset($form);
      $user_input = $form_state->getUserInput();

      // This is important to not interfere with Drupal's form caching.
      unset($user_input['form_build_id']);

      // Make sure to never submit using the Save button.
      unset($user_input['op']);
      if (isset($user_input['_triggering_element_name'])) {
        // The triggering element will be replaced by a simulated one below.
        $user_input['_triggering_element_name'] = '_eca_simulated_submit';
        $user_input['_triggering_element_value'] = '_eca_simulated_submit';
        $user_input['_eca_simulated_submit'] = '_eca_simulated_submit';
      }

      // Keep the current errors in mind.
      $any_errors = FormState::hasAnyErrors();
      $current_errors = $form_state->getErrors();
      $form_state->clearErrors();

      // Keep the currently stored list of messages in mind.
      // The form build will add messages to the messenger, which we want
      // to clear from the runtime.
      $messenger = $this->messenger();
      $messages_by_type = $messenger->all();

      $form_state = new FormState();
      $form_object = clone $form_object;

      $form_state->setFormObject($form_object);
      $form_state->setUserInput($user_input);

      // Flag this form state, to prevent recursion with other (or the same)
      // configured ECA components.
      $form_state->set('skip_eca', TRUE);
      $form_state->set('eca_skip_manual_build', TRUE);
      $form_state->setProgrammed();
      $form_state->disableCache();

      try {
        $form = $this->formBuilder->retrieveForm($form_object->getFormId(), $form_state);

        // The form is being build up without ECA involved, because skip_eca was
        // set above. It may now be the case, that a custom button was added
        // with ECA, meaning that button would not be available here anymore.
        // That may lead to errors, especially known with file and image
        // fields. To prevent that, the following section makes sure, that a
        // triggering element always exists.
        $form['_eca_simulated_submit'] = [
          '#type' => 'submit',
          '#name' => '_eca_simulated_submit',
          '#value' => '_eca_simulated_submit',
        ];
        // Remove action submit buttons, to make sure the save button will not
        // be involved by any means.
        unset($form['actions']);

        $this->formBuilder->prepareForm($form_object->getFormId(), $form, $form_state);
        $form_state->setTriggeringElement($form['_eca_simulated_submit']);
        $this->formBuilder->processForm($form_object->getFormId(), $form, $form_state);
        $form_state->cleanValues();
      }
      finally {
        // Make sure that the real form state will have its errors restored.
        (function () use ($any_errors, $current_errors) {
          /** @var \Drupal\Core\Form\FormState $this */
          // @phpstan-ignore-next-line
          $this::setAnyErrors($any_errors);
          // @phpstan-ignore-next-line
          $this->errors = $current_errors;
        })(...)->call($this->getCurrentFormState());

        // Now re-add the previously fetched messages.
        $messenger->deleteAll();
        foreach ($messages_by_type as $messageType => $messages) {
          foreach ($messages as $message) {
            $messenger->addMessage($message, $messageType);
          }
        }
      }
    }

    // No submission means no changed values. Therefore, no rebuild is needed.
    // However we always need to clone this one, to be consistent to the
    // behavior of \Drupal\Core\Entity\EntityFormInterface::buildEntity().
    $can_build = ($form_state->getValues() && ($form_state->isSubmitted() || ($form_state->isRebuilding() && $form_state->isValidationComplete()) || $needs_manual_build));
    $entity = $can_build ? $form_object->buildEntity($form, $form_state) : clone $form_object->getEntity();

    $this->tokenService->addTokenData($this->configuration['token_name'], $entity);
  }

}
