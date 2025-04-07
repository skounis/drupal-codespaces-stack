<?php

namespace Drupal\ai_agents_form_integration\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_agents_form_integration\Service\FormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field Type creation.
 */
class FieldTypeCreation extends FormBase {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI Provider Plugin Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Module Handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\ai_agents_form_integration\Service\FormHelper $aiFormHelper
   *   The Form Helper for AI Agents.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProvider,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormHelper $aiFormHelper,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('ai_agents_form_integration.form_helper'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'ai_agents_form_integration_field_type_generation';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $bundle = NULL) {

    $form['entity'] = [
      '#type' => 'hidden',
      '#value' => $entity_type_id,
    ];

    $form['bundle'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('The description of the field types, this is optional if you upload a file. This is either a prompt that explains what you want to create or a helper prompt to fill in the gaps for how you want to create the content type based on the document uploaded.'),
      '#attributes' => [
        'placeholder' => $this->t('Add an media field for images called "Cool Images" that allows for multiple images to be uploaded and an string list field where I can choose among the 10 most popular tourist spots in the world.'),
      ],
    ];

    $missing = [];
    $allowed_file_types = [
      'csv',
      'txt',
    ];
    if ($this->aiProvider->getDefaultProviderForOperationType('chat_with_image_vision')) {
      $allowed_file_types[] = 'jpg';
      $allowed_file_types[] = 'jpeg';
      $allowed_file_types[] = 'png';
    }
    else {
      $missing[] = 'set a default provider for Chat with Image Vision';
    }

    // Check if unstructured is installed.
    if ($this->moduleHandler->moduleExists('unstructured')) {
      $allowed_file_types[] = 'pdf';
      $allowed_file_types[] = 'doc';
      $allowed_file_types[] = 'docx';
      $allowed_file_types[] = 'ppt';
      $allowed_file_types[] = 'pptx';
      $allowed_file_types[] = 'xls';
      $allowed_file_types[] = 'xlsx';
    }
    else {
      $missing[] = 'install the Unstructured module';
    }

    if (!empty($missing)) {
      $this->messenger()->addWarning($this->t('The following need to be done before you can use certain file types on this form: %missing.', [
        '%missing' => implode(', ', $missing),
      ]));
    }

    $form['document'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Document'),
      '#upload_location' => 'public://',
      '#description' => $this->t('The file that contains the context for the field types. This is optional if you provide a prompt. Currently takes the following files: %file_types.', [
        '%file_types' => implode(', ', $allowed_file_types),
      ]),
      '#upload_validators' => [
        'file_validate_extensions' => [implode(' ', $allowed_file_types)],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Fields'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $prompt = $form_state->getValue('prompt');
    $document = $form_state->getValue('document');
    if (empty($prompt) && empty($document)) {
      $form_state->setErrorByName('prompt', $this->t('You must provide a prompt or a document. One of them is required.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form_state->getValue('entity');
    $bundle = $form_state->getValue('bundle');
    // Get the file type.
    $file = $form_state->getValue('document');
    $file = $file[0];
    /** @var \Drupal\file\Entity\File */
    $file = $this->entityTypeManager->getStorage('file')->load($file);
    $file_path = $file->getFileUri();

    $data = [];
    switch ($file->getMimeType()) {
      case 'text/csv':
        $data = $this->getBatchDataForCsv($file_path);
        break;
    }
    if (count($data)) {
      // Start batch jobs.
      $batch = [
        'title' => $this->t('Generating Fields'),
        'finished' => '\Drupal\ai_agents_form_integration\Batch\FieldTypeGeneration::finished',
      ];
      foreach ($data as $row) {
        $row = 'For the entity type ' . $entity . ' and bundle ' . $bundle . " generate a field using the following context. Some of the data given might no be of importance.:\n---------------------------------\n" . $row . "\n---------------------------------";
        $batch['operations'][] = [
          '\Drupal\ai_agents_form_integration\Batch\FieldTypeGeneration::generateField',
          [$row, $entity, $bundle],
        ];
      }
      batch_set($batch);
    }
  }

  /**
   * Figure out CSV.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return array
   *   The data.
   */
  public function getBatchDataForCsv(string $file_path) {
    // Figure out if it has a header.
    $has_header = $this->aiFormHelper->csvHasHeader($file_path);
    // Figure out if its a structure or data.
    $sample = $this->isCsvStructure($file_path);
    $data = [];
    // If its not structured data, it has to analyze everything.
    if (!$sample) {
    }
    else {
      // We get each row.
      $rows = $this->aiFormHelper->getCsvAsArray($file_path);
      // If it has a header, we cleanup the data.
      if ($has_header) {
        $header = array_shift($rows);
        // We get the data.
        foreach ($rows as $row) {
          $parts = array_combine($header, $row);
          $tmp = [];
          foreach ($parts as $key => $value) {
            $tmp[] = $key . ': ' . $value;
          }
          $data[] = implode("\n", $tmp);
        }
      }
      else {
        // We get the data.
        foreach ($rows as $row) {
          $data[] = implode(", ", $row);
        }
      }
    }
    return $data;
  }

  /**
   * Figure out if a CSV file is an data set of fields or a data set of data.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return bool
   *   If the CSV seems to be a data set of fields.
   */
  public function isCsvStructure(string $file_path): bool {
    $sample = $this->aiFormHelper->getSampleFromCsv($file_path, 4, FALSE);
    $prompt = "The following is an excerpt of the first 4 lines of a CSV file. I am trying to create field in Drupal, can you tell me if the dataset of each row is a field description or if they are just data and the user wants to create field types based on that data? Answer field_description if its a field description, otherwise answer row_data.\n\ncsv file:\n";
    $prompt .= $sample;
    $default = $this->aiProvider->getDefaultProviderForOperationType('chat_with_complex_json');
    $provider = $this->aiProvider->createInstance($default['provider_id']);
    $response = $provider->chat(new ChatInput([
      new ChatMessage('user', $prompt),
    ]), $default['model_id']);
    return $response->getNormalized()->getText() === 'field_description';
  }

}
