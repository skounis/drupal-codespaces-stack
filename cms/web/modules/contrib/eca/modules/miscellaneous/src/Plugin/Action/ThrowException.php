<?php

namespace Drupal\eca_misc\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Loads a query argument from the request into the token environment.
 *
 * @Action(
 *   id = "eca_throw_exception",
 *   label = @Translation("Throw exception"),
 *   description = @Translation("Throws an exception that won't be caught by ECA."),
 *   eca_version_introduced = "2.1.3"
 * )
 */
class ThrowException extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function handleExceptions(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function logExceptions(): bool {
    return $this->configuration['log_exception'];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $class = $this->configuration['exception_type'];
    $message = $this->tokenService->replace($this->configuration['response_message']);
    throw new $class($message);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exception_type' => '',
      'response_message' => '',
      'log_exception' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['exception_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Exception type'),
      '#options' => [
        AccessDeniedHttpException::class => $this->t('Access denied'),
        BadRequestHttpException::class => $this->t('Bad request'),
        ConflictHttpException::class => $this->t('Conflict'),
        GoneHttpException::class => $this->t('Gone'),
        LengthRequiredHttpException::class => $this->t('Length required'),
        LockedHttpException::class => $this->t('Locked'),
        NotAcceptableHttpException::class => $this->t('Not acceptable'),
        NotFoundHttpException::class => $this->t('Not found'),
        PreconditionFailedHttpException::class => $this->t('Precondition failed'),
        PreconditionRequiredHttpException::class => $this->t('Precondition required'),
        UnprocessableEntityHttpException::class => $this->t('Unprocessable entity'),
        UnsupportedMediaTypeHttpException::class => $this->t('Unsupported media type'),
      ],
      '#default_value' => $this->configuration['exception_type'],
      '#required' => TRUE,
    ];
    $form['response_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response message'),
      '#description' => $this->t('An optional message explaining why the exception was thrown.'),
      '#default_value' => $this->configuration['response_message'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['log_exception'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log as an error'),
      '#description' => $this->t('If enabled, the exception will be logged as an error.'),
      '#default_value' => $this->configuration['log_exception'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['exception_type'] = $form_state->getValue('exception_type');
    $this->configuration['response_message'] = $form_state->getValue('response_message');
    $this->configuration['log_exception'] = $form_state->getValue('log_exception');
    parent::submitConfigurationForm($form, $form_state);
  }

}
