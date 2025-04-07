<?php

namespace Drupal\ai_agents_form_integration\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Service\FieldAgent\FieldAgentHelper;
use Drupal\ai_agents\Task\Task;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Content Types form.
 */
class ContentTypes extends FormBase {

  /**
   * The crawler.
   *
   * @var \Drupal\simple_crawler\Crawler|null
   */
  protected $crawler;

  /**
   * The unstructured api.
   *
   * @var \Drupal\unstructured\UnstructuredApi|null
   */
  protected $unstructuredApi;

  /**
   * Constructor.
   */
  public function __construct(
    protected AiAgentManager $aiAgentManager,
    protected AiProviderPluginManager $aiProvider,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PromptJsonDecoderInterface $promptJsonDecoder,
    protected FieldAgentHelper $fieldAgentHelper,
    $crawler = NULL,
    $unstructuredApi = NULL,
  ) {
    $this->crawler = $crawler;
    $this->unstructuredApi = $unstructuredApi;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $crawler = $container->has('simple_crawler.crawler') ? $container->get('simple_crawler.crawler') : NULL;
    $unstructured_api = $container->has('unstructured.api') ? $container->get('unstructured.api') : NULL;
    return new static(
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai_agents.field_agent_helper'),
      $crawler,
      $unstructured_api,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'ai_agents_form_integration_content_types';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the content type, leave empty if you want AI to come up with one. If you have documents that could allude to having multiple content types, this name helps in figuring out which one you want to generate.'),
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('The description of the content type, this is optional if you upload a file. This is either a prompt that explains what you want to create or a helper prompt to fill in the gaps for how you want to create the content type based on the document uploaded.'),
      '#attributes' => [
        'placeholder' => $this->t('I want to generate a hotel review content type that has a title, address, a fivestar rating field and review free text field. All fields should be required.'),
      ],
    ];

    $valid_extensions = 'jpg jpeg png gif';
    if ($this->unstructuredApi) {
      $valid_extensions .= ' pdf doc docx txt ppt pptx xls xlsx';
    }

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Context File'),
      '#upload_location' => 'public://',
      '#description' => $this->t('The file that contains the context for the content type. This is optional if you provide a description.'),
      '#upload_validators' => [
        'file_validate_extensions' => [$valid_extensions],
      ],
    ];

    $form['vocabulary_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate Vocabulary'),
      '#description' => $this->t('Generate a vocabulary if there is no fitting vocabulary for a taxonomy field.'),
      '#default_value' => TRUE,
    ];

    if (!$this->unstructuredApi) {
      $form['info_file'] = [
        '#markup' => $this->t('The <a href="https://www.drupal.org/project/unstructured" target="_blank">Unstructured</a> module is not enabled, only images are supported as files.<br>'),
      ];
    }

    if ($this->crawler) {
      $form['website'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Website'),
        '#description' => $this->t('The website to scrape for context. This only works with a prompt.'),
        '#attributes' => [
          'placeholder' => $this->t('https://www.example.com'),
        ],
      ];
    }
    else {
      $form['info_website'] = [
        '#markup' => $this->t('The <a href="https://www.drupal.org/project/simple_crawler" target="_blank">Simple Crawler</a> module is not enabled, website scraping is not supported.<br>'),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Content Type'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $prompt = $form_state->getValue('prompt');
    $file = $form_state->getValue('file');
    $website = $form_state->getValue('website');
    if (empty($prompt) && empty($file)) {
      $form_state->setErrorByName('prompt', $this->t('You must provide a prompt or a file. One of them is required.'));
    }
    if (!empty($website) && empty($prompt)) {
      $form_state->setErrorByName('website', $this->t('You must provide a prompt to scrape a website.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $agent = $this->aiAgentManager->createInstance('node_content_type_agent');

    $prompt = "Based on the following possible title and prompt can you generate a content type for me?";
    $prompt .= "\n\nTitle: " . $form_state->getValue('title');
    $prompt .= "\n\nPrompt: " . $form_state->getValue('prompt');

    $task = new Task($prompt);
    $agent->setTask($task);
    $default = $this->aiProvider->getDefaultProviderForOperationType('chat');
    $agent->setAiProvider($this->aiProvider->createInstance($default['provider_id']));
    $agent->setModelName($default['model_id']);
    $agent->setAiConfiguration([]);
    $agent->setCreateDirectly(TRUE);
    $solvability = $agent->determineSolvability();
    if (!$solvability) {
      $context['results']['error'] = "The AI agent could not solve the task.";
      return;
    }
    $agent->solve();
    $solution = $agent->getData();

    if (is_string($solution)) {
      $context['results']['error'] = $solution;
      return;
    }
    if (!isset($solution[0]['data_name'])) {
      $context['results']['error'] = "The AI agent could not solve the task.";
      return;
    }

    try {

      $bundle = $solution[0]['data_name'];

      // Check if the body storage field exists.
      $field_storage = $this->entityTypeManager
        ->getStorage('field_storage_config')
        ->load('node.body');
      // If it does not exist, create it.
      if (!$field_storage) {
        $this->entityTypeManager
          ->getStorage('field_storage_config')
          ->create([
            'field_name' => 'body',
            'entity_type' => 'node',
            'type' => 'text_with_summary',
            'settings' => [
              'display_summary' => TRUE,
            ],
          ])
          ->save();
      }
      // Attach the body field to the content type.
      $this->entityTypeManager->getStorage('field_config')->create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => 'Body',
      ])->save();

      if ($fieldContext = $this->generateFieldContext($form_state->getValue('prompt'), $form_state->getValue('file'), $form_state->getValue('website'))) {
        // Create a batch job.
        $batch = [
          'title' => $this->t('Generating Content Type'),
          'finished' => '\Drupal\ai_agents_form_integration\Batch\ContentTypeGeneration::generateContentTypeFinished',
        ];
        foreach ($fieldContext as $fieldPrompt) {
          $batch['operations'][] = [
            '\Drupal\ai_agents_form_integration\Batch\ContentTypeGeneration::generateFieldType',
            [
              $fieldPrompt,
              $bundle,
              $form_state->getValue('vocabulary_generate'),
            ],
          ];
        }
        batch_set($batch);
      }
    }
    catch (\Exception $e) {
      $context['results']['error'] = "The AI agent could not solve the task.";
      $this->logger('ai_agents_form_integration')->error($e->getMessage());
    }

    $form_state->setRedirect('entity.node_type.collection');
  }

  /**
   * Generate field context.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $file_id
   *   The file id.
   * @param string $website
   *   The website.
   *
   * @return array
   *   The fields.
   */
  public function generateFieldContext($prompt, $file_id, $website) {
    // If its a file we look into the file.
    $file_context = "";
    $fields = [];

    $default = $this->aiProvider->getDefaultProviderForOperationType('chat');
    $provider = $this->aiProvider->createInstance($default['provider_id']);

    // If no file and no website, we use the prompt.
    if (empty($file_id) && empty($website)) {
      $prompt = "Based on the following prompt, could you extract the possible fields and describe them in maximum two sentences and what they do and with an example of each field. Respond with a json array with the field names and descriptions. Respond with a json array with the field names and descriptions.\n\n
      " . $this->extraData() . "

      $prompt";
      $message = new ChatMessage("user", $prompt);
      $input = new ChatInput([$message]);
      $result = $provider->chat($input, $default['model_id'])->getNormalized();
      $fields = $this->promptJsonDecoder->decode($result);
      return $fields;
    }

    if (!empty($file_id)) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')->load($file_id[0]);
      if (!in_array($file->getMimeType(), [
        'image/jpeg',
        'image/png',
        'image/gif',
      ]) && class_exists('\Drupal\unstructured\Formatters\MarkdownFormatter')) {
        // Run unstructured data extraction.
        $data = $this->unstructuredApi->structure($file);
        // @codingStandardsIgnoreLine
        $format = new \Drupal\unstructured\Formatters\MarkdownFormatter();
        $text = $format->format($data, "all");
        if (isset($text[0])) {
          $file_context = $text[0];
          $message = new ChatMessage("user",
            "Based on the following prompt and the following context of a file, could you extract the possible fields and describe them in maximum two sentences and what they do and with an example of each field. Respond with a json array with the field names and descriptions. Respond with a json array with the field names and descriptions.

" . $this->extraData() . "

Prompt:
$prompt

File Context:
$file_context");
          $input = new ChatInput([$message]);
          $result = $provider->chat($input, $default['model_id'])->getNormalized();
          $fields = $this->promptJsonDecoder->decode($result);
        }
      }
      else {
        // Prepare image data for prompt.
        $images = [];
        $image = new ImageFile();
        $images[] = $image->setFileFromFile($file);
        $message = new ChatMessage("user", "Can you look at the image and describe it in such a way that you can create a content type and fields from it. Explain the fields one by one each on a new row. Be verbose. Make up a name for the content type. The image might depict instructions or might be general data that we want to generate a content type from. Respond with a json array with the field names and descriptions.

" . $this->extraData() . "

Prompt:
$prompt

```", $images);
        $input = new ChatInput([$message]);
        $result = $provider->chat($input, $default['model_id'])->getNormalized();
        $fields = $this->promptJsonDecoder->decode($result);
      }
    }

    if ($website) {
      $page = $this->crawler->scrapePageAsBrowser($website, FALSE);

      $converter = new HtmlConverter();
      $page = $converter->convert($page);
      $message = new ChatMessage("user", "Based on the following prompt and the following context of a website, could you extract the possible fields and describe them in maximum two sentences and what they do and with an example of each field. Respond with a json array with the field names and descriptions.

" . $this->extraData() . "

Prompt:
$prompt

Website Context:
$page");
      $input = new ChatInput([$message]);
      $result = $provider->chat($input, $default['model_id'])->getNormalized();
      $fields = $this->promptJsonDecoder->decode($result);
    }
    return $fields;
  }

  /**
   * Extra data for the prompt.
   *
   * @return string
   *   The extra data.
   */
  public function extraData() {
    // Get all vocabularies.
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocab_as_context = '';
    foreach ($vocabularies as $vocabulary) {
      $vocab_as_context .= "Vocabulary: " . $vocabulary->label() . "(" . $vocabulary->id() . ")\n";
    }
    return "The field name should be in natural language, with spaces and not more then 255 characters.

You will be given a list of field types, think about the prompt and the context of the document/webpage/image and what field type it should be.

Images should in general be media field with image when it makes sense.
Categories should be entity_reference field type with the description that it should be a taxonomy if they look clickable.

Do not use custom field or fivestar field.

For the title, author, created and updated you do not have to set fields, since they already exists on the node.

Also try to figure out from context if the field should be required or not. If no information is given, try to think about if it makes sense to be required or not.

If the field is a taxonomy term, you should use the vocabulary id in the description. If it does not exist, use the vocabulary key to create a new vocabulary.

List of vocabularies:\n" . $vocab_as_context . "

Each object should have a key called fieldName, fieldType, description, with a string value for each key and a vocabulary key if needed. Like this
```json
[
    \"fieldName\": \"Title\",
    \"fieldType\": \"string\",
    \"description\": \"The title of the page.\",
    \"required\": true
  },
  {
    \"fieldName\": \"Body\",
    \"fieldType\": \"text_long\",
    \"description\": \"The body of the page, should only be generated once.\",
    \"required\": true
  },
  {
    \"fieldName\": \"Tags\",
    \"fieldType\": \"entity_reference\",
    \"description\": \"A taxonomy field that uses tags as vocabulary.\",
    \"required\": false
  },
  {
    \"fieldName\": \"Cars\",
    \"fieldType\": \"entity_reference\",
    \"description\": \"A taxonomy field that uses cars as vocabulary.\",
    \"vocabulary\": \"cars\",
    \"required\": false
  }
]
```

List of field types:\n" . $this->fieldAgentHelper->getFieldTypesList();
  }

}
