<?php

namespace Drupal\ai_eca\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Describes the AI ai_eca_execute_chat action.
 *
 * @Action(
 *   id = "ai_eca_execute_chat",
 *   label = @Translation("Chat"),
 *   description = @Translation("Run text through the AI chat model.")
 * )
 */
class Chat extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'prompt' => 'Enter your prompt for AI here.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'],
      '#description' => $this->t('Enter your text here. When submitted, AI will generate a response from its Chats endpoint. Based on the complexity of your text, AI traffic, and other factors, a response can sometimes take up to 10-15 seconds to complete. Please allow the operation to finish. Be cautious not to exceed the requests per minute quota (20/Minute by default), or you may be temporarily blocked.'),
      '#required' => TRUE,
    ];

    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $description */
    $description = $form['config']['#description'];
    $description = $this->t(
      // phpcs:ignore
      sprintf('%s<br/>The "profile" helps set the behavior of the LLM response. You can change/influence how it response by adjusting the system prompt. Eg. <pre>system_name: system<br>system_prompt: you are a helpful assistant</pre>', $description->getUntranslatedString()),
      $description->getArguments(),
      $description->getOptions(),
    );
    $form['config']['#description'] = $description;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['prompt'] = $form_state->getValue('prompt');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $modelData = $this->getModelData();
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\OperationType\Chat\ChatInterface $provider */
    $provider = $this->loadModelProvider();

    $data = [];
    if (
      !empty($this->configuration['token_input'])
      && $this->tokenService->hasTokenData($this->configuration['token_input'])
    ) {
      $token_value = $this->tokenService->getTokenData($this->configuration['token_input']);
      $data = [
        $this->configuration['token_input'] => $token_value,
      ];
    }

    $prompt = $this->tokenService->replace($this->configuration['prompt'], $data);

    $chatInput = [
      new chatMessage('user', $prompt),
    ];
    $modelConfig = $this->getModelConfig();
    if (!empty($modelConfig['system_name']) && !empty($modelConfig['system_prompt'])) {
      $chatInput[] = new ChatMessage($modelConfig['system_name'], $modelConfig['system_prompt']);
      unset($modelConfig['system_name'], $modelConfig['system_prompt']);
    }

    $messages = new ChatInput($chatInput);
    $provider->setConfiguration($this->getModelConfig());
    $message = $provider->chat($messages, $modelData['model_id'])->getNormalized();
    $response = trim($message->getText()) ?? $this->t('No result could be generated.');

    $this->tokenService->addTokenData($this->configuration['token_result'], $response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'chat';
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtraConstraints(): array {
    return [
      'system_name' => new Optional([
        'constraints' => [new Type('string')],
      ]),
      'system_prompt' => new Optional([
        'constraints' => [new Type('string')],
      ]),
    ];
  }

}
