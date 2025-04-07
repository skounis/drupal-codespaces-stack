<?php

namespace Drupal\ai_agents\Form;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Configure on AI Agent.
 */
class AiAgentPromptChanger extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_agents.settings';

  /**
   * The AI Agents Plugin Manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected $agentsManager;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * Constructor.
   */
  final public function __construct(AiAgentManager $agents_manager, ExtensionPathResolver $extension_path_resolver) {
    $this->agentsManager = $agents_manager;
    $this->extensionPathResolver = $extension_path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_agents'),
      $container->get('extension.path.resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_agent_prompt_changer';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $agent_id = NULL, $file = NULL) {
    if (!$agent_id) {
      throw new \InvalidArgumentException('Agent ID is required.');
    }
    if (!$file) {
      throw new \InvalidArgumentException('File is required.');
    }

    $config = $this->config(static::CONFIG_NAME);

    $form['agent'] = [
      '#type' => 'value',
      '#value' => $agent_id,
    ];

    $form['file'] = [
      '#type' => 'value',
      '#value' => $file,
    ];

    $definition = $this->agentsManager->getDefinition($agent_id);
    $module = $definition['provider'];
    $file_path = $this->extensionPathResolver->getPath('module', $module) . '/prompts/' . $agent_id . '/' . $file;
    $file_value = Yaml::parse(file_get_contents($file_path));
    $output_value = Yaml::dump($file_value['prompt'], 20, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    $id = str_replace('.', '', $file);
    $actual_config = $config->get('agent_settings')['agent_id'] ?? [];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('YAML File'),
      '#default_value' => $actual_config['yaml_override'][$id] ?? $output_value,
      '#attributes' => [
        'rows' => 30,
      ],
    ];

    if (isset($actual_config['yaml_override'][$id])) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset file'),
        '#weight' => 100,
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#weight' => 99,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::CONFIG_NAME);

    // Check if the reset button was pressed.
    if ($form_state->getTriggeringElement()['#id'] === 'edit-reset') {
      return;
    }
    // Check so the YAML is valid.
    try {
      Yaml::parse($form_state->getValue('prompt'));
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('prompt', $this->t('The YAML is not valid.'));
    }
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::CONFIG_NAME);

    $id = str_replace('.', '', $form_state->getValue('file'));
    $agent_config = $config->get('agent_settings')[$form_state->getValue('agent')] ?? [];
    // Check if the reset button was pressed.
    if ($form_state->getTriggeringElement()['#id'] === 'edit-reset') {
      if (isset($agent_config['yaml_override'][$id])) {
        unset($agent_config['yaml_override'][$id]);
      }
    }
    else {
      $definition = $this->agentsManager->getDefinition($form_state->getValue('agent'));
      $module = $definition['provider'];
      $file = $this->extensionPathResolver->getPath('module', $module) . '/prompts/' . $form_state->getValue('agent') . '/' . $form_state->getValue('file');
      $file_value = file_get_contents($file);
      // Check so something changed.
      if ($form_state->getValue('prompt') !== $file_value) {
        $agent_config['yaml_override'][$id] = $form_state->getValue('prompt');
      }
    }
    $agent_settings = $config->get('agent_settings');
    $agent_settings[$form_state->getValue('agent')]['prompt'] = $form_state->getValue('prompt');
    $config->set('agent_settings', $agent_settings);
    $config->save();

  }

}
