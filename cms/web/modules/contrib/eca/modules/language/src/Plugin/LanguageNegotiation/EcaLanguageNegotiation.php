<?php

namespace Drupal\eca_language\Plugin\LanguageNegotiation;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Negotiates the language to use with ECA.
 *
 * @LanguageNegotiation(
 *   id = \Drupal\eca_language\Plugin\LanguageNegotiation\EcaLanguageNegotiation::METHOD_ID,
 *   weight = -20,
 *   name = @Translation("ECA"),
 *   description = @Translation("Event-based language negotiation with ECA.")
 * )
 */
final class EcaLanguageNegotiation extends LanguageNegotiationMethodBase implements ContainerFactoryPluginInterface {

  /**
   * The language negotiation method id.
   */
  public const METHOD_ID = 'eca';

  /**
   * The trigger event helper.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static();
    $instance->triggerEvent = $container->get('eca.trigger_event');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    $langcode = NULL;

    if ($event = $this->triggerEvent->dispatchFromPlugin('eca_language:negotiate')) {
      $langcode = !empty($event->langcode) ? $event->langcode : $langcode;
    }

    return $langcode;
  }

}
