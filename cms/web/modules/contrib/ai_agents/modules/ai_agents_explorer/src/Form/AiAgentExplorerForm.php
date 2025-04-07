<?php

namespace Drupal\ai_agents_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Run AI Agents.
 */
class AiAgentExplorerForm extends FormBase {

  /**
   * The AI Provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The AI Agents Plugin Manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected $agentsManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $ai_manager, AiAgentManager $agents_manager, MessengerInterface $messenger) {
    $this->providerManager = $ai_manager;
    $this->agentsManager = $agents_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_agents_explorer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $options = [];
    foreach ($this->agentsManager->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }
    asort($options);

    $form['#attached']['library'][] = 'ai_agents_explorer/explore';

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ai-agents-explorer-form'],
      ],
    ];
    $default_agent = NULL;
    if ($this->getRequest()->query->has('agent_id')) {
      $default_agent = $this->getRequest()->query->get('agent_id');
    }
    if (isset($this->getRequest()->getSession()->get('ai_agent_explorer')['agent']) && !$default_agent) {
      $default_agent = $this->getRequest()->getSession()->get('ai_agent_explorer')['agent'];
    }

    $form['wrapper']['agent'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Agent'),
      '#description' => $this->t('Select the agent to use.'),
      '#required' => TRUE,
      '#default_value' => $default_agent,
    ];

    $form['wrapper']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Enter the prompt to send to the agent.'),
      '#required' => TRUE,
      '#default_value' => $this->getRequest()->getSession()->get('ai_agent_explorer')['prompt'] ?? NULL,
    ];

    $form['wrapper']['image'] = [
      '#type' => 'managed_file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Images'),
      '#multiple' => TRUE,
      '#description' => $this->t('If you want to feed the agent with images, upload them here. Requires a vision model.'),
    ];

    $default = $this->providerManager->getDefaultProviderForOperationType('chat_with_complex_json');

    $form['wrapper']['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#description' => $this->t('Choose the model to use.'),
      '#required' => TRUE,
      '#options' => $this->providerManager->getSimpleProviderModelOptions('chat', TRUE, TRUE, [AiModelCapability::ChatJsonOutput]),
      '#default_value' => $default['provider_id'] . '__' . $default['model_id'],
    ];

    $form['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Agent'),
    ];

    $form['progress'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ai-agents-explorer-progress'],
      ],
    ];

    $form['progress']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Progress') . '</h3>',
    ];

    $form['progress']['messages'] = [
      '#type' => 'markup',
      '#markup' => '<table class="explorer-messages"><tr><th>' . $this->t('Step') . '</th><th>' . $this->t('Time from start (s)') . '</th></tr></table>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
