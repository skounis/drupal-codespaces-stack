<?php

namespace Drupal\eca_user\Plugin\ECA\Event;

use Drupal\Core\Session\AccountEvents;
use Drupal\Core\Session\AccountSetEvent;
use Drupal\eca\Attributes\Token;
use Drupal\eca\Event\Tag;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Drupal\eca_user\Event\UserBase;
use Drupal\eca_user\Event\UserCancel;
use Drupal\eca_user\Event\UserEvents;
use Drupal\eca_user\Event\UserLogin;
use Drupal\eca_user\Event\UserLogout;
use Drupal\user\Event\UserEvents as CoreUserEvents;
use Drupal\user\Event\UserFloodEvent;

/**
 * Plugin implementation of the ECA Events for users.
 *
 * @EcaEvent(
 *   id = "user",
 *   deriver = "Drupal\eca_user\Plugin\ECA\Event\UserEventDeriver",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class UserEvent extends EventBase {

  /**
   * {@inheritdoc}
   */
  public static function definitions(): array {
    return [
      'login' => [
        'label' => 'Login of a user',
        'event_name' => UserEvents::LOGIN,
        'event_class' => UserLogin::class,
        'tags' => Tag::WRITE | Tag::EPHEMERAL | Tag::AFTER,
      ],
      'logout' => [
        'label' => 'Logout of a user',
        'event_name' => UserEvents::LOGOUT,
        'event_class' => UserLogout::class,
        'tags' => Tag::WRITE | Tag::EPHEMERAL | Tag::AFTER,
      ],
      'cancel' => [
        'label' => 'Cancelling a user',
        'event_name' => UserEvents::CANCEL,
        'event_class' => UserCancel::class,
        'tags' => Tag::CONTENT | Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'floodblockip' => [
        'label' => 'Flood blocked IP',
        'event_name' => CoreUserEvents::FLOOD_BLOCKED_IP,
        'event_class' => UserFloodEvent::class,
        'tags' => Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'floodblockuser' => [
        'label' => 'Flood blocked user',
        'event_name' => CoreUserEvents::FLOOD_BLOCKED_USER,
        'event_class' => UserFloodEvent::class,
        'tags' => Tag::WRITE | Tag::PERSISTENT | Tag::AFTER,
      ],
      'set_user' => [
        'label' => 'Set current user',
        'event_name' => AccountEvents::SET_USER,
        'event_class' => AccountSetEvent::class,
        'tags' => Tag::WRITE | Tag::RUNTIME | Tag::AFTER,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'account',
    description: 'The user entity of the event.',
    classes: [UserBase::class, AccountSetEvent::class],
    aliases: ['entity'],
  )]
  #[Token(
    name: 'account',
    description: 'The flooding user entity.',
    classes: [UserFloodEvent::class],
    aliases: ['entity'],
  )]
  public function getData(string $key): mixed {
    if (in_array($key, ['entity', 'account'], TRUE)) {
      $event = $this->event;
      if ($event instanceof UserBase || $event instanceof AccountSetEvent) {
        $account_id = $event->getAccount()->id();
      }
      elseif ($event instanceof UserFloodEvent) {
        $account_id = $event->getUid();
      }
      else {
        $account_id = NULL;
      }
      if (isset($account_id) && ($user = $this->entityTypeManager->getStorage('user')->load($account_id))) {
        return $user;
      }
    }
    return parent::getData($key);
  }

}
