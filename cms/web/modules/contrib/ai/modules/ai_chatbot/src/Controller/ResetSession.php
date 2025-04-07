<?php

namespace Drupal\ai_chatbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Creates a chatbot message skeleton for twig.
 */
class ResetSession extends ControllerBase {

  /**
   * Constructs a new GetSkeleton object.
   */
  public function __construct(
    protected AiAssistantApiRunner $aiAssistantApiRunner,
  ) {
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_assistant_api.runner'),
    );
  }

  /**
   * Resets the sessions of the chatbot for a thread for the user.
   *
   * @param string $assistant_id
   *   The assistant id to reset.
   * @param string $thread_id
   *   The thread id to reset.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   Return the message skeleton.
   */
  public function resetSession(string $assistant_id, string $thread_id) {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant */
    $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($assistant_id);
    if (!$assistant) {
      return new JsonResponse([
        'success' => FALSE,
      ]);
    }
    $this->aiAssistantApiRunner->setAssistant($assistant);
    $new_thread_id = $this->aiAssistantApiRunner->resetThread($thread_id);

    return new JsonResponse([
      'success' => TRUE,
      'thread_id' => $new_thread_id,
    ]);
  }

}
