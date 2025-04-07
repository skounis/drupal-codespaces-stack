<?php

namespace Drupal\ai_agents\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Agents.
 */
class AiAgentsSettingsForm extends FormBase {

  /**
   * The AI Agents Plugin Manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected $agentsManager;

  /**
   * Constructor.
   */
  final public function __construct(AiAgentManager $agents_manager) {
    $this->agentsManager = $agents_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_agents')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_agents_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $header = [
      'id' => $this->t('ID'),
      'label' => $this->t('Agent'),
      'description' => $this->t('Description'),
      'edit' => $this->t('Edit'),
    ];

    $rows = [];
    foreach ($this->agentsManager->getDefinitions() as $id => $value) {
      $instance = $this->agentsManager->createInstance($id);
      $rows[] = [
        'id' => $id,
        'label' => $instance->agentsCapabilities()[$id]['name'],
        'description' => $instance->agentsCapabilities()[$id]['description'],
        'edit' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Edit'),
            '#url' => Url::fromRoute('ai_agents.setting_form', [
              'agent_id' => $id,
            ]),
          ],
        ],
      ];
    }

    $form['agents'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
