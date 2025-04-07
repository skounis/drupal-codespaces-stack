<?php

namespace Drupal\eca_language\Plugin\Action;

use Drupal\eca_language\Event\LanguageNegotiateEvent;

/**
 * Resets language negotiation.
 *
 * @Action(
 *   id = "eca_reset_language_negotiation",
 *   label = @Translation("Language: reset negotiation"),
 *   description = @Translation("This may be useful when switching between multiple users with different preferred languages."),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class ResetLanguageNegotiation extends LanguageActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (isset($this->event) && ($this->event instanceof LanguageNegotiateEvent)) {
      $this->event->langcode = NULL;
      return;
    }
    $this->languageManager->getNegotiator()->setCurrentUser($this->currentUser);
    $this->languageManager->reset();
  }

}
