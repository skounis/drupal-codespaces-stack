<?php

namespace Drupal\ai_agents_extra\Service\WebformAgent;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Service\AgentHelper;
use Drupal\webform\Plugin\WebformElementManager;
use Symfony\Component\Yaml\Yaml;

/**
 * All the actions to take on a webform.
 */
class WebformActions {

  /**
   * The agent helper.
   *
   * @var \Drupal\ai_agents\Service\AgentHelper
   */
  protected $agentHelper;

  /**
   * The base yaml.
   *
   * @var array
   *   The base yaml.
   */
  public $baseYaml;

  /**
   * The example yaml.
   *
   * @var string
   *   The example yaml.
   */
  protected $exampleYaml;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The webform element.
   *
   * @var Drupal\webform\Plugin\WebformElementManager
   */
  protected $webformElement;

  /**
   * WebFormActions constructor.
   *
   * @param \Drupal\ai_agents\Service\AgentHelper $agentHelper
   *   The agent helper.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    AgentHelper $agentHelper,
    ExtensionPathResolver $extensionPathResolver,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->agentHelper = $agentHelper;
    $this->entityTypeManager = $entityTypeManager;
    $this->exampleYaml = Yaml::parse(file_get_contents($extensionPathResolver->getPath('module', 'ai_agents') . '/resources/webform_example.yml'));
  }

  /**
   * Sets webform element manager.
   *
   * @param \Drupal\webform\Plugin\WebformElementManager $webformElement
   *   The webform element.
   */
  public function setWebformElement(WebformElementManager $webformElement) {
    $this->webformElement = $webformElement;
  }

  /**
   * Setup the actions before triggering them.
   */
  public function setup($yaml, AiAgentInterface $agent) {
    $this->baseYaml = $yaml;
    $this->agentHelper->setupRunner($agent);
  }

  /**
   * Generate form.
   *
   * @param array $fields
   *   The fields.
   *
   * @return \Drupal\webform\Entity\Webform
   *   The webform.
   */
  public function generateFirstWebForm($fields) {
    // Generate unique id first.
    if (empty($this->baseYaml['id']) || $this->baseYaml['id'] == 'randomized') {
      $random = new Random();
      $this->baseYaml['id'] = md5($random->string(16, TRUE));
    }
    // Remove some things.
    $this->baseYaml['handlers'] = [];
    $this->baseYaml['elements'] = [];

    // Then add as we go.
    foreach ($fields as $field) {
      if (!empty($field['id'])) {
        $this->generateFormField($field);
      }
      elseif (!empty($field['type'])) {
        $this->generateFormSetting($field);
      }
    }
    $webForm = $this->entityTypeManager->getStorage('webform')->create($this->baseYaml);
    $webForm->save();
    return $webForm;
  }

  /**
   * Save function.
   *
   * @return \Drupal\webform\Entity\Webform|null
   *   The webform.
   */
  public function save() {
    if ($this->baseYaml['id']) {
      /** @var \Drupal\webform\Entity\Webform */
      $webform = $this->entityTypeManager->getStorage('webform')->load($this->baseYaml['id']);
      // Edit the webform based on the YAML.
      $webform->set('elements', $this->baseYaml['elements']);
      $webform->set('settings', $this->baseYaml['settings']);
      $webform->set('title', $this->baseYaml['title']);
      $webform->set('description', $this->baseYaml['description']);
      $webform->set('handlers', $this->baseYaml['handlers']);
      $webform->save();
      return $webform;
    }
    return NULL;
  }

  /**
   * Generate form from set Yaml.
   *
   * @param array $data
   *   The data.
   *
   * @return \Drupal\webform\Entity\Webform
   *   The webform.
   */
  public function generateContextualizedWebForm($data) {
    $this->baseYaml = $data;
    $webForm = $this->entityTypeManager->getStorage('webform')->create($this->baseYaml);
    $webForm->save();
    return $webForm;
  }

  /**
   * Form action to not understand anything.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionNone($data) {
    return "Sorry, I could not understand your request.";
  }

  /**
   * Remove field action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionRemoveField($data) {
    $result = $this->agentHelper->runSubAgent('removeField', [
      'YAML' => $this->baseYaml['elements'],
    ]);
    if (!empty($result[0]['keys'])) {
      $elements = Yaml::parse($this->baseYaml['elements']);
      $keys = explode(',', $data[0]['keys']);
      foreach ($keys as $key) {
        unset($elements[$key]);
      }
      return $data[0]['response'];
    }
    return "Couldn't remove the requested fields.";
  }

  /**
   * Move field action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionMoveField($data) {
    $data = $this->agentHelper->runSubAgent('moveField', [
      'YAML' => $this->baseYaml['elements'],
    ]);

    $newElements = [];
    $this->baseYaml['elements'] = Yaml::parse($this->baseYaml['elements']);
    if (!empty($data)) {
      if (!isset($data[0])) {
        $data = [$data];
      }
      foreach ($data as $part) {
        foreach ($part['order'] as $key) {
          $newElements[$key] = $this->baseYaml['elements'][$key];
        }
      }
      return $data[0]['response'];
    }
    return "Couldn't move the requested fields.";
  }

  /**
   * Change url action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionChangeUrl($data) {
    $data = $this->agentHelper->runSubAgent('changeUrl', []);
    if (!empty($data)) {
      if (!isset($data[0])) {
        $data = [$data];
      }
      $settings = $this->baseYaml['settings'];
      $settings['page_submit_path'] = $data[0]['url'];
      return $data[0]['response'];
    }
    return "Could change the url, maybe it was not valid?";
  }

  /**
   * Change emails action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionChangeEmails($data) {
    $data = $this->agentHelper->runSubAgent('changeEmails', []);

    if (!empty($data)) {
      if (!isset($data[0])) {
        $data = [$data];
      }
      $handlers = $this->baseYaml['handlers'];
      foreach ($data as $row) {
        if ($row['action'] == 'add') {
          foreach (explode(',', $row['emails']) as $mail) {
            $mail = trim($mail);
            $template = $this->exampleYaml['handlers']['email'];
            $template['settings']['to_mail'] = $mail;
            $handlers[md5($mail)] = $template;
          }
        }
        elseif ($row['action'] == 'remove') {
          if ($row['emails'] == 'none') {
            $handlers = [];
          }
          else {
            foreach (explode(',', $row['emails']) as $mail) {
              $mail = trim($mail);
              if (isset($handlers[md5($mail)])) {
                unset($handlers[md5($mail)]);
              }
            }
          }
        }
      }
      return "We changed how the email is set.";
    }
    return "Could change the url, maybe it was not valid?";
  }

  /**
   * Change title action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionChangeTitle($data) {
    $data = $this->agentHelper->runSubAgent('changeTitle', []);
    if (!empty($data)) {
      if (!isset($data[0])) {
        $data = [$data];
      }
      return $data[0]['response'];
    }
    return "Could change the url, maybe it was not valid?";
  }

  /**
   * Change field action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionUpdateField($data) {
    $data = $this->agentHelper->runSubAgent('updateField', [
      'YAML' => $this->baseYaml['elements'],
    ]);
    if (!empty($data)) {
      // The elements are YAML inside YAML and needs to be parsed again.
      $this->baseYaml['elements'] = Yaml::parse($this->baseYaml['elements']);
      if (!isset($data[0])) {
        $data = [$data];
      }
      foreach ($data as $field) {
        // Make sure its just update.
        $field['update'] = TRUE;
        $field['prompt'] = TRUE;
        $this->generateFormField($field);
      }
      // Dump it again before saving it.
      $this->baseYaml['elements'] = Yaml::dump($this->baseYaml['elements']);
      return $data[0]['response'];
    }
    return "Couldn't update the requested fields.";
  }

  /**
   * Add field action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionAddField($data) {
    $data = $this->agentHelper->runSubAgent('addField', [
      'YAML' => $this->baseYaml['elements'],
    ]);
    if (!empty($data)) {
      // The elements are YAML inside YAML and needs to be parsed again.
      $this->baseYaml['elements'] = Yaml::parse($this->baseYaml['elements']);
      if (!isset($data[0])) {
        $data = [$data];
      }
      foreach ($data as $field) {
        $this->generateFormField($field);
      }
      // Dump it again before saving it.
      $this->baseYaml['elements'] = Yaml::dump($this->baseYaml['elements']);

      return $data[0]['response'];
    }
    return "Couldn't remove the requested fields.";
  }

  /**
   * Set status.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionStatus($data) {
    $result = $this->agentHelper->runSubAgent('status', [
      'Additional data needed' => $data['description'],
    ]);
    if (!empty($result[0]['status'])) {
      return $result[0]['response'];
    }
    return "Couldn't understand how you want the publish status to be.";
  }

  /**
   * Get information action.
   *
   * @param array $data
   *   The data.
   *
   * @return string
   *   The response.
   */
  public function formActionGetInformation($data) {
    $result = $this->agentHelper->runSubAgent('getInformation', []);
    if (isset($result['choices'][0]['message']['content'])) {
      $data = json_decode($result['choices'][0]['message']['content'], TRUE);
      $response = "";
      if (!empty($data[0]['action'])) {
        foreach ($data as $ask) {
          switch ($ask['action']) {
            case 'url':
              $url = $this->baseYaml['settings']['page_submit_path'];
              $response .= '<p>The url is <a href="' . $url . '">' . $url . "</a>.</p>";
              break;

            case 'title':
              $response .= '<p>The title is <strong>' . $this->baseYaml['title'] . '</strong>.</p>';
              break;

            case 'publish_status':
              $response .= '<p>The status is <strong>' . $this->baseYaml['status'] . '</strong>.</p>';
              break;

            case 'emails':
              $email = [];
              foreach ($this->baseYaml['handlers'] as $handler) {
                if ($handler['id'] == 'email') {
                  $email[] = str_replace([
                    '[webform_submission:values:',
                    ':raw]',
                  ], '', $handler['settings']['to_mail']);
                }
              }
              if (count($email)) {
                $response .= '<p>The form will send an email to the following emails or fields: <strong>' . implode(", ", $email) . '</strong>.</p>';
              }
              else {
                $response .= '<p>The form will not send an email to anyone.</p>';
              }
              break;
          }
        }
      }
      if ($response) {
        return $response;
      }
    }
    return "Couldn't understand what you were asking for.";
  }

  /**
   * Generate a form field.
   */
  public function generateFormField(array $field) {
    $fieldType = $this->getFieldType($field);
    if ($fieldType) {
      $fieldType = str_replace(' ', '', $fieldType);
      $newField = [
        '#type' => $fieldType,
        '#title' => $field['title'],
        '#description' => $field['description'],
        '#required' => $field['required'],
      ];
      if (!empty($field['options'])) {
        $options = explode(';', $field['options']);
        $newField['#options'] = $options;
      }
      $newField = $this->getFormattedElement($newField);
      // If we are just updating.
      if (!empty($field['update'])) {
        $this->baseYaml['elements'][$field['id']] = $newField;
      }
      // If we need to sort it.
      elseif (!empty($field['follows'])) {
        // Find position of element before.
        $pos = array_search($field['follows'], array_keys($this->baseYaml['elements'])) + 1;
        // Use array slice to bind it.
        $this->baseYaml['elements'] = array_slice($this->baseYaml['elements'], 0, $pos, TRUE) +
          [$field['id'] => $newField] + array_slice($this->baseYaml['elements'], $pos, (count($this->baseYaml['elements']) - 1), TRUE);
      }
      else {
        $this->baseYaml['elements'][$field['id']] = $newField;
      }
    }
  }

  /**
   * Generate a form field.
   */
  public function generateFormSetting(array $field) {
    switch ($field['type']) {
      case 'set_title':
        $this->baseYaml['title'] = $field['value'];
        break;

      case 'set_description':
        $this->baseYaml['description'] = $field['value'];
        break;

      case 'set_url':
        $this->baseYaml['settings']['page_submit_path'] = $field['value'];
        break;
    }
  }

  /**
   * Special cases.
   */
  public function getFormattedElement($formatted) {
    switch ($formatted['#type']) {
      case 'captcha':
        $formatted['#captcha_type'] = 'recaptcha/reCAPTCHA';
        break;

      case 'tel':
        $formatted['#international'] = TRUE;
        break;
    }
    return $formatted;
  }

  /**
   * Figure out type of field.
   */
  public function getFieldType($fieldData) {
    $data = "";
    foreach ($this->getPossibleFields() as $fieldType) {
      $data .= "$fieldType[label]\nid: $fieldType[id]\ndescription: $fieldType[description]\n\n";
    }

    if (empty($fieldData['options'])) {
      $data = $this->agentHelper->runSubAgent('fieldType', [
        'Data' => $data,
      ]);
    }
    else {
      $data = $this->agentHelper->runSubAgent('fieldTypeOptions', [
        'Data' => $data,
      ]);
    }

    return $data[0]['field_type'] ?? "";
  }

  /**
   * Get possible fields.
   *
   * @return array
   *   The possible fields for the AI.
   */
  public function getPossibleFields() {
    return $this->webformElement->getDefinitions();
  }

  /**
   * Get possible fields.
   *
   * @return array
   *   The possible fields for the AI.
   */
  public function getPossibleFieldNames() {
    $names = [];
    foreach ($this->webformElement->getDefinitions() as $definition) {
      $names[$definition['label']] = $definition['label'];
    }
    return array_keys($names);
  }

}
