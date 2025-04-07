<?php

namespace Drupal\ai_agents\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Link;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Configure on AI Agent.
 */
class AiAgentSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_agents.settings';

  /**
   * Constructor.
   */
  final public function __construct(
    protected AiAgentManager $agentsManager,
    protected ExtensionPathResolver $extensionPathResolver,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_agents'),
      $container->get('extension.path.resolver'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_agent_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state, $agent_id = NULL) {
    if (!$agent_id) {
      throw new \InvalidArgumentException('Agent ID is required.');
    }
    $form = [];

    $config = $this->config(static::CONFIG_NAME);

    $form['agent'] = [
      '#type' => 'value',
      '#value' => $agent_id,
    ];

    $agent_config = $config->get('agent_settings')[$agent_id] ?? [];
    $instance = $this->agentsManager->createInstance($agent_id);
    $data = $instance->agentsCapabilities()[$agent_id];

    $form['name'] = [
      '#markup' => '<h2>Name: ' . $data['name'] . '</h2>',
    ];

    $form['metadata'] = [
      '#type' => 'details',
      '#title' => 'Metadata',
    ];

    $form['metadata']['description'] = [
      '#markup' => '<strong>Description:</strong> ' . $data['description'] . '<br>',
    ];

    $inputs = '';
    foreach ($data['inputs'] as $key => $input) {
      $inputs .= '* ' . $input['name'] . ' (id: ' . $key . ', type: ' . $input['type'] . ') - ' . $input['description'] . '<br>';
    }

    $form['metadata']['inputs'] = [
      '#markup' => '<strong>Inputs:</strong><br>' . $inputs,
    ];

    $outputs = '';
    foreach ($data['outputs'] as $key => $output) {
      $outputs .= '* ' . $key . ' (type: ' . $output['type'] . ') - ' . $output['description'] . '<br>';
    }

    $form['metadata']['outputs'] = [
      '#markup' => '<strong>Outputs:</strong><br>' . $outputs,
    ];

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    $options = [];
    foreach ($roles as $key => $role) {
      $options[$key] = $role->label();
    }

    $usage_instructions = $agent_config['usage_instructions'] ?? '';
    if (!$usage_instructions) {
      $usage_instructions = $data['usage_instructions'] ?? '';
    }
    $form['usage_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Usage instructions'),
      '#description' => $this->t('These instructions will be given to any tool like the Assistants API that uses the instructions.'),
      '#default_value' => $usage_instructions,
    ];

    $form['permissions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $options,
      '#description' => $this->t('Select the roles that can use this agent.'),
      '#default_value' => $agent_config['permissions'] ?? [],
    ];

    $form['prompts'] = [
      '#type' => 'details',
      '#title' => 'Prompts configuration',
    ];

    $definition = $this->agentsManager->getDefinition($agent_id);
    $module = $definition['provider'];
    $dir = $this->extensionPathResolver->getPath('module', $module) . '/prompts/' . $agent_id;
    if (is_dir($dir) === TRUE) {
      $files = [];
      foreach (scandir($dir) as $file) {
        if (is_file($dir . '/' . $file)) {
          $id = str_replace('.', '', $file);
          $data = Yaml::parseFile($dir . '/' . $file);
          $data['file'] = $file;
          if (!isset($data['name'])) {
            $data['name'] = $file;
          }
          $data['id'] = $id;
          $files[] = $data;

        }
      }

      // Resort the files on the key weight inside or it existing.
      uasort($files, function ($a, $b) {
        $t = isset($a['weight']) && isset($b['weight']) ? $a['weight'] <=> $b['weight'] : 0;
        if (isset($a['weight']) && !isset($b['weight'])) {
          $t = -1;
        }
        if (!isset($a['weight']) && isset($b['weight'])) {
          $t = 1;
        }
        return $t;
      });

      foreach ($files as $data) {
        $id = $data['id'];
        $name = $data['name'];
        $is_triage = !empty($data['is_triage']) ?? FALSE;
        $description = $data['description'] ?? $this->t('No description available.');
        $link = Link::createFromRoute('override', 'ai_agents.prompt_changer', [
          'agent_id' => $agent_id,
          'file' => $data['file'],
        ]);
        $info = '<h3>' . $name . '</h3>';
        $info .= '<strong>' . $this->t('Triage:') . '</strong> ' . ($is_triage ? $this->t('Yes') : $this->t('No'));
        $info .= '<br><strong>' . $this->t('Description:') . '</strong> ' . nl2br($description);
        $info .= '<br><strong>' . $this->t('File:') . '</strong> ' . $dir . '/' . $data['file'] . ' (' . $link->toString() . ')</a>';
        $form['prompts'][$id] = [
          '#type' => 'fieldset',
        ];
        $form['prompts'][$id]['info'] = [
          '#markup' => $info,
        ];
        $form['prompts'][$id]['actions'] = [
          '#type' => 'details',
          '#title' => $this->t('Actions'),
        ];
        $markup = '<h4>' . $this->t('No Actions') . '</h4>';
        if (isset($data['prompt']['possible_actions'])) {
          $markup = '<h4>' . $this->t('Possible actions') . '</h4>';
          $markup .= '<ul>';
          foreach ($data['prompt']['possible_actions'] as $name => $description) {
            $markup .= '<li>' . $name . ' - ' . $description . '</li>';
          }
          $markup .= '</ul>';
        }
        $form['prompts'][$id]['actions']['action_markup'] = [
          '#markup' => $markup,
        ];

        $form['prompts'][$id][$id] = [
          '#type' => 'textarea',
          '#title' => $this->t('Extra instructions'),
          '#description' => $this->t('These instructions will be given to the agent outside of the normal instructions.'),
          '#default_value' => $agent_config['extra_instructions'][$id] ?? '',
        ];
      }
    }

    $form['plugin_settings'] = [
      '#type' => 'details',
      '#title' => 'Plugin settings',
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    // Load the agents plugin form as a subform.
    $subform = $form['plugin_settings']['plugin'] ?? [];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    $form['plugin_settings']['plugin'] = $instance->buildConfigurationForm($subform, $subform_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::CONFIG_NAME);

    $newConfig = [];
    $newConfig['usage_instructions'] = $form_state->getValue('usage_instructions');
    foreach ($form_state->getValues() as $id => $value) {
      if (preg_match('/(yml|yaml)$/', $id) && $value) {
        $newConfig['extra_instructions'][$id] = $value;
      }
      if ($id === 'permissions') {
        $newConfig['permissions'] = $value;
      }
    }
    $subform = $form['plugin_settings']['plugin'] ?? [];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    $instance = $this->agentsManager->createInstance($form_state->getValue('agent'));
    $instance->submitConfigurationForm($subform, $subform_state);
    $newConfig['plugin_settings'] = $instance->getConfiguration();
    $agent_settings = $config->get('agent_settings');
    $agent_settings[$form_state->getValue('agent')] = $newConfig;
    $config->set('agent_settings', $agent_settings);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
