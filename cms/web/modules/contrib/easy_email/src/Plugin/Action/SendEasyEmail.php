<?php

namespace Drupal\easy_email\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\easy_email\Service\EmailHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Send Easy Email action.
 *
 * @Action(
 *   id = "easy_email_send",
 *   label = @Translation("Send Easy Email"),
 *   type = "easy_email",
 *   category = @Translation("Easy Email")
 * )
 */
class SendEasyEmail extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The easy_email handler to send emails.
   *
   * @var EmailHandlerInterface
   */
  protected EmailHandlerInterface $emailHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EmailHandlerInterface $emailHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->emailHandler = $emailHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ActionBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('easy_email.handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($email, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $node */
    if ($return_as_object) {
      return AccessResult::allowed();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->emailHandler->sendEmail($entity);
  }

}
