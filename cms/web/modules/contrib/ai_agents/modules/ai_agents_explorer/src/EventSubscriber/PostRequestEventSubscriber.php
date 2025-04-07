<?php

namespace Drupal\ai_agents_explorer\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai_agents_explorer\EventSubscriber
 */
class PostRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI settings.
   *
   * @var ImmutableConfig
   */
  protected $aiSettings;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The JSON Prompt handler.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoderInterface
   *   The JSON Prompt handler.
   */
  protected $jsonPromptHandler;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, PromptJsonDecoderInterface $jsonPromptHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiSettings = $configFactory->get('ai_logging.settings');
    $this->moduleHandler = $moduleHandler;
    $this->jsonPromptHandler = $jsonPromptHandler;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'logAgentPostRequest',
    ];
  }

  /**
   * Log if needed after running an AI request.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   */
  public function logAgentPostRequest(PostGenerateResponseEvent $event) {
    if (in_array('ai_agents', $event->getTags())) {
      $file = '';
      $runner_id = '';
      foreach ($event->getTags() as $tag) {
        if (strpos($tag, 'ai_agents_prompt_') !== FALSE) {
          $file = str_replace('ai_agents_prompt_', '', $tag);
        }
        if (strpos($tag, 'ai_agents_runner_') !== FALSE) {
          $runner_id = str_replace('ai_agents_runner_', '', $tag);
        }
      }
      $storage = $this->entityTypeManager->getStorage('ai_agent_decision');
      $response = $this->jsonPromptHandler->decode($event->getOutput()->getNormalized());
      $formatted = is_array($response) ? Json::encode($response) : $response;
      $decision = $storage->create([
        'label' => 'Ran ' . $file . '.yaml',
        'runner_id' => $runner_id,
        'microtime' => microtime(TRUE),
        'action' => 'sub_agent',
        'log_status' => 'notice',
        'question' => $event->getInput()->getMessages()[0]->getText(),
        'prompt_used' => $event->getDebugData()['chat_system_role'] ?? '',
        'response_given' => is_string($formatted) ? $formatted : $formatted->getText(),
        'detailed_output' => Json::encode($event->getOutput()->getRawOutput()),
      ]);
      $decision->save();
    }
  }

}
