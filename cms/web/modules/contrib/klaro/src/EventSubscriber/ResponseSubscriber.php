<?php

namespace Drupal\klaro\EventSubscriber;

use Drupal\klaro\Utility\KlaroHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to the kernel response event.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * The Klaro Helper instance.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaroHelper;

  /**
   * The constructor.
   *
   * @param \Drupal\klaro\Utility\KlaroHelper $klaro_helper
   *   The messenger service.
   */
  public function __construct(KlaroHelper $klaro_helper) {
    $this->klaroHelper = $klaro_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = 'onKernelResponse';
    return $events;
  }

  /**
   * Let klaroHelper modify the Html of the response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Response event.
   */
  public function onKernelResponse(ResponseEvent $event) {
    // Do nothing if not activated.
    $config = $this->klaroHelper->getSettings();
    $inspect_only = FALSE;

    if (!$config->get('auto_decorate_final_html')) {
      if ($config->get('log_unknown_resources')) {
        $inspect_only = TRUE;
      }
      else {
        return;
      }
    }

    // Do nothing if not main request.
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }

    // Do nothing if on disabled uri.
    // @todo how to determine this for ajax-requests?
    if ($this->klaroHelper->onDisabledUri($event->getRequest())) {
      return;
    }

    $response = $event->getResponse();

    // Modify AjaxCommand responses.
    if (get_class($response) === 'Drupal\Core\Ajax\AjaxResponse') {
      foreach ($response->getCommands() as &$cmd) {
        $cmd = $this->klaroHelper->handleAjaxCommand($cmd, $inspect_only);
      }
      return;
    }

    // Further only process text/html responses.
    $ct_type = $response->headers->get('content-type');
    if (!$ct_type || strpos($ct_type, "text/html") === FALSE) {
      return;
    }

    $content = $response->getContent();
    if (!empty($content)) {
      $response->setContent($this->klaroHelper->processHtml($content, $inspect_only));
    }
  }

}
