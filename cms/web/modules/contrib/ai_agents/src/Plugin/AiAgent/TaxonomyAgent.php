<?php

namespace Drupal\ai_agents\Plugin\AiAgent;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_agents\Attribute\AiAgent;
use Drupal\ai_agents\PluginBase\AiAgentBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Plugin implementation of the Taxonomy Agent.
 */
#[AiAgent(
  id: 'taxonomy_agent',
  label: new TranslatableMarkup('Taxonomy Agent'),
)]
class TaxonomyAgent extends AiAgentBase {

  use DependencySerializationTrait;

  /**
   * Questions to ask.
   *
   * @var array
   */
  protected $questions;

  /**
   * Information to inform.
   *
   * @var string
   */
  protected $information;

  /**
   * The full data of the initial task.
   *
   * @var array
   */
  protected $data;

  /**
   * Task type.
   *
   * @var string
   */
  protected $taskType;

  /**
   * The result.
   *
   * @var array
   */
  protected array $result = [];

  /**
   * The created terms.
   *
   * @var array
   */
  protected $createdTerms = [];

  /**
   * The edited terms.
   *
   * @var array
   */
  protected $editedTerms = [];

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return 'taxonomy_agent';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      'Taxonomy Agent',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [
      'taxonomy_agent' => [
        'name' => 'Taxonomy Agent',
        'description' => "This agent is capable of creating new vocabularies, editing & renaming vocabularies and adding, editing or reordering taxonomy terms to vocabularies. Note that it does not setup taxonomy fields on other entities or answer question about fields on the vocabulary.",
        'usage_instructions' => "If a user asked you to Categorize something. Assume you will need to use a taxonomy in Drupal. If they ask you to create a category or taxonomy, assume they will also want you to create a field that uses that taxonomy on an entity they have been talking about.\nIf you are unsure ask, before you create a taxonomy without attaching it to an entity. ALWAYS try and add any newly created taxonomy vocabularies to an entity using the entity reference field so that they can select the taxonomy in the edit form. If you are unable to ALWAYS ask.\nYou are allowed to suggest taxonomy terms for the vocabulary, if the user asks to generate based on your knowledge.",
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'The prompt to create, edit, delete or ask questions about vocabulary types or taxonomy terms.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about the taxonomy or the content type generated.',
            'type' => 'string',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function setData($data) {
    $this->data[] = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function isAvailable() {
    // Check if taxonomy module is installed.
    return $this->agentHelper->isModuleEnabled('taxonomy');
  }

  /**
   * {@inheritDoc}
   */
  public function isNotAvailableMessage() {
    return $this->t('You need to enable the taxonomy module to use this.');
  }

  /**
   * {@inheritDoc}
   */
  public function getRetries() {
    return 2;
  }

  /**
   * {@inheritDoc}
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritDoc}
   */
  public function answerQuestion() {
    if (isset($this->data[0]['information'])) {
      return $this->data[0]['information'];
    }
    $data = $this->agentHelper->runSubAgent('answerTaxonomyQuestion', [
      'The list of vocabularies' => $this->getVerboseVocabulariesAsString(),
    ]);

    $answer = "";
    if (isset($data[0]['information'])) {
      foreach ($data as $dataPoint) {
        $answer .= $dataPoint['information'] . "\n";
      }
      return $answer;
    }

    return $this->t("Sorry, I got no answers for you.");
  }

  /**
   * {@inheritDoc}
   */
  public function askQuestion() {
    return implode("\n", $this->questions);
  }

  /**
   * {@inheritDoc}
   */
  public function inform() {
    return $this->information;
  }

  /**
   * {@inheritDoc}
   */
  public function getHelp() {
    $help = $this->t("This agent can answer questions about vocabularies and taxonomy terms.");
    return $help;
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess() {
    // Check for permissions.
    if (!$this->currentUser->hasPermission('administer taxonomy')) {
      return AccessResult::forbidden();
    }
    return parent::hasAccess();
  }

  /**
   * {@inheritDoc}
   */
  public function determineSolvability() {
    parent::determineSolvability();
    $this->taskType = $this->determineTypeOfTask();
    switch ($this->taskType) {
      case 'create_vocabulary':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'create_taxonomy_term':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'edit_vocabulary':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'edit_taxonomy_term':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'question_vocabulary':
        return AiAgentInterface::JOB_SOLVABLE;

      case 'delete_vocabulary':
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'delete_taxonomy_term':
        return AiAgentInterface::JOB_NEEDS_ANSWERS;

      case 'information':
        return AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION;

      case 'fail':
        return AiAgentInterface::JOB_INFORMS;
    }

    return AiAgentInterface::JOB_NOT_SOLVABLE;
  }

  /**
   * {@inheritDoc}
   */
  public function solve() {
    $messages = [];
    $messages[] = '';
    foreach ($this->data as $data) {
      switch ($data['action']) {
        case 'create_vocabulary':
          try {
            $this->createVocabulary($data);
          }
          catch (\Exception $e) {
            $messages[] = 'There was an error creating the node type: ' . $e->getMessage();
          }
          break;

        case 'create_taxonomy_term':
          try {
            $this->createTerm($data);
          }
          catch (\Exception $e) {
            $messages[] = 'There was an error creating the node type: ' . $e->getMessage();
          }
          break;

        case 'edit_vocabulary':
          try {
            $this->editVocabulary($data);
          }
          catch (\Exception $e) {
            $messages[] = 'There was an error editing the node type: ' . $e->getMessage();
          }
          break;

        case 'edit_taxonomy_term':
          try {
            $this->editTerm($data);
          }
          catch (\Exception $e) {
            $messages[] = 'There was an error editing the node type: ' . $e->getMessage();
          }
          break;
      }
    }
    $message = '';
    if (count($messages)) {
      $message .= "The following are errors:\n" . implode("\n", $messages);
    }
    if (count($this->result)) {
      $message .= "The following are results:\n" . implode("\n", $this->result) . "\n";
    }
    $listTermUrls = [];
    if (count($this->createdTerms)) {
      $message .= "The following terms were created:\n";
      $i = 0;
      foreach ($this->createdTerms as $term) {
        if ($i > 10) {
          $message .= "And more...\n\n";
          break;
        }
        $i++;
        $message .= $term['name'] . ' - ' . $term['id'] . ' - ' . $term['vid'] . "\n";
        $listTermUrls[$term['vid']] = $term['vid'];
      }
    }
    if (count($this->editedTerms)) {
      $message .= "The following terms were edited:\n";
      $i = 0;
      foreach ($this->editedTerms as $term) {
        if ($i > 10) {
          $message .= "And more...\n\n";
          break;
        }
        $message .= $term['name'] . ' - ' . $term['id'] . ' - ' . $term['vid'] . "\n";
      }
      $listTermUrls[$term['vid']] = $term['vid'];
    }
    if (count($listTermUrls)) {
      $message .= "You can see the terms here:\n";
      foreach ($listTermUrls as $vid) {
        $url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', [
          'taxonomy_vocabulary' => $vid,
        ])->toString();
        $message .= $url . "\n";
      }
    }
    return $message;
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
    $this->data[0]['action'] = 'create';
  }

  /**
   * Check so all requirements are there.
   *
   * @return bool
   *   If all requirements are there.
   */
  public function checkRequirements() {
    return TRUE;
  }

  /**
   * Determine if the context is asking a question or wants a audit done.
   *
   * @return string
   *   The context.
   */
  public function determineTypeOfTask() {
    $data = $this->agentHelper->runSubAgent('determineTaxonomyTask', [
      'All currently available vocabularies' => $this->getVerboseVocabulariesAsString(),
    ]);
    // Return error early.
    if (!isset($data[0]['action'])) {
      $this->information = 'Sorry, we could not understand what you wanted to do, please try again.';
      return [
        ['action' => 'fail'],
      ];
    }

    // Check if we need to make a second taxonomy term request.
    $new_data = [];
    $new_data_done = FALSE;
    $unset_keys = [];
    foreach ($data as $key => $data_point) {
      if ($new_data_done) {
        continue;
      }
      if ($data_point['action'] == 'manipulate_taxonomy_terms' && !empty($data_point['data_name'])) {
        $unset_keys[] = $key;
        $new_data = $this->agentHelper->runSubAgent('determineTermAction', [
          'The vocabulary in question' => $this->getVerboseVocabulariesAsString($data_point['data_name']),
          'All currently available taxonomy terms on the vocabulary' => $this->getVerboseTaxonomyTermsAsString($data_point['data_name']),
        ]);
        $new_data_done = TRUE;
      }
      if ($data_point['action'] == 'question_vocabulary' && !empty($data_point['data_name'])) {
        $unset_keys[] = $key;
        $new_data = $this->agentHelper->runSubAgent('answerTaxonomyQuestion', [
          'The vocabulary in question' => $this->getVerboseVocabulariesAsString($data_point['data_name']),
          'All currently available taxonomy terms on the vocabulary' => $this->getVerboseTaxonomyTermsAsString($data_point['data_name']),
        ]);
        $new_data_done = TRUE;
      }
    }

    $data = array_merge($data, $new_data);

    // Remove the unset keys.
    foreach ($unset_keys as $unset_key) {
      unset($data[$unset_key]);
    }
    // Reset the keys.
    $data = array_values($data);

    // Add the questions.
    if (!empty($data[0]['information'])) {
      $this->questions[] = $data[0]['information'];
    }

    if (!empty($data[0]['fail_reason'])) {
      $this->information = $data[0]['fail_reason'];
    }

    // If its not allowed actions.
    if ($data[0]['action'] == 'delete_vocabulary' || $data[0]['action'] == 'delete_taxonomy_term') {
      $this->information = 'You are not allowed to delete vocabularies or taxonomy terms. Please ask an admin to help you or ask me how you can do it manually.';
      return 'fail';
    }

    $this->data = $data;
    return $data[0]['action'];
  }

  /**
   * Create a new vocabulary.
   *
   * @param array $data
   *   The data to create the vocabulary.
   *
   * @throws \Exception
   *   If the vocabulary could not be created.
   */
  public function createVocabulary($data) {
    $vocabulary = Vocabulary::create([
      'vid' => $data['data_name'],
      'name' => $data['readable_name'],
      'description' => $data['description'],
      'langcode' => $data['vocabulary_language'],
      'new_revision' => $data['create_new_revision'],
      'uid' => $this->currentUser->id(),
    ]);
    if ($vocabulary->save()) {
      // Link to the vocabulary page.
      $url = Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString();
      $this->result[] = $this->t('The vocabulary @name has been created. You can see it here: @url', [
        '@url' => $url,
        '@name' => $data['readable_name'],
      ]);
      // Add it to the structured output.
      $this->structuredResultData->setCreatedConfig($vocabulary);
    }
    else {
      throw new \Exception('The vocabulary could not be created.');
    }
  }

  /**
   * Create terms.
   *
   * @param array $data
   *   The data to create the term.
   *
   * @throws \Exception
   *   If the terms could not be created.
   */
  public function createTerm($data) {
    // Double check if the vocabulary exists.
    $vocabulary = Vocabulary::load($data['data_name']);
    if (!$vocabulary) {
      // Try to find the vocabulary using LLM.
      $vocabulary_exists = $this->agentHelper->runSubAgent('vocabularyExists', [
        'All currently available vocabularies' => $this->getVerboseVocabulariesAsString(),
      ]);
      if (empty($vocabulary_exists[0]['action']) || $vocabulary_exists[0]['action'] !== 'found') {
        throw new \Exception('The vocabulary does not exist.');
      }
      $data['data_name'] = $vocabulary_exists[0]['data_name'];
    }
    $term = Term::create([
      'name' => $data['readable_name'],
      'vid' => $data['data_name'],
      'description' => $data['description'],
      'langcode' => $data['taxonomy_term_language'],
      'parent' => $data['parent_term_id'],
      'weight' => $data['weight'],
      'uid' => $this->currentUser->id(),
    ]);
    if ($term->save()) {
      $this->createdTerms[] = [
        'id' => $term->id(),
        'name' => $term->label(),
        'vid' => $term->vid->target_id,
      ];
      $this->structuredResultData->setCreatedContent($term);
    }
    else {
      throw new \Exception('The term could not be created.');
    }
  }

  /**
   * Edit a vocabulary.
   *
   * @param array $data
   *   The data to edit the vocabulary.
   *
   * @throws \Exception
   *   If the vocabulary could not be edited.
   */
  public function editVocabulary($data) {
    $vocabulary = Vocabulary::load($data['data_name']);
    $this->setOriginalConfigurations($vocabulary);
    foreach ([
      'name' => 'readable_name',
      'description' => 'description',
    ] as $key => $change) {
      if (isset($data[$change])) {
        $vocabulary->set($key, $data[$change]);
      }
    }
    if ($vocabulary->save()) {
      $diff = $this->getDiffOfConfigurations($vocabulary);
      $this->structuredResultData->setEditedConfig($vocabulary, $diff);
      $url = Url::fromRoute('entity.taxonomy_vocabulary.collection', [
        'taxonomy_vocabulary' => $data['data_name'],
      ])->toString();
      $this->result[] = $this->t('The vocabulary %name has been edited, check out the settings here %url.', [
        '%name' => $vocabulary->label(),
        '%url' => $url,
      ]);
    }
    else {
      throw new \Exception('The vocabulary could not be edited.');
    }
  }

  /**
   * Edit a term.
   *
   * @param array $data
   *   The data to edit the term.
   *
   * @throws \Exception
   *   If the term could not be edited.
   */
  public function editTerm($data) {
    $term = Term::load($data['taxonomy_term_id']);
    $this->setOriginalEntity($term);
    if (!$term) {
      throw new \Exception('The term could not be found.');
    }
    // Check if the terms has the language.
    if (isset($data['taxonomy_term_language'])) {
      if ($term->hasTranslation($data['taxonomy_term_language'])) {
        $term = $term->getTranslation($data['taxonomy_term_language']);
      }
      else {
        $term = $term->addTranslation($data['taxonomy_term_language']);
      }
    }
    foreach ([
      'name' => 'readable_name',
      'description' => 'description',
      'langcode' => 'taxonomy_term_language',
      'parent' => 'parent_term_id',
      'weight' => 'weight',
    ] as $key => $data_key) {
      if (isset($data[$data_key])) {
        $term->set($key, $data[$data_key]);
      }
    }
    if ($term->save()) {
      $this->structuredResultData->setEditedContent($term);
      $this->editedTerms[] = [
        'id' => $term->id(),
        'name' => $term->label(),
        'vid' => $term->vid->target_id,
      ];
    }
    else {
      throw new \Exception('The term could not be edited.');
    }
  }

  /**
   * Get all the vocabularies.
   *
   * @return array
   *   The vocabularies.
   */
  public function getVocabularies() {
    $vocabularies = Vocabulary::loadMultiple();
    $vocabulariesList = [];
    foreach ($vocabularies as $vocabulary) {
      $vocabulariesList[$vocabulary->id()] = $vocabulary->label();
    }
    return $vocabulariesList;
  }

  /**
   * Get all the vocabularies as a string with verbose information.
   *
   * @return string
   *   The vocabularies as a string.
   */
  public function getVerboseVocabulariesAsString($vid = NULL) {
    $vocabularies = $this->getVocabularies();
    $list = "";
    foreach ($vocabularies as $dataName => $vocabulary) {
      if ($vid && $dataName != $vid) {
        continue;
      }
      // Load the vocabulary.
      $vocabulary = Vocabulary::load($dataName);

      // Show all the configurations.
      $list .= $vocabulary->label() . ' - dataname: ' . $dataName . "\n";
      $list .= 'Description: ' . $vocabulary->getDescription() . "\n";
      $list .= 'Vocabulary Language: ' . $vocabulary->get('langcode') . "\n";
      $list .= 'New Revision: ' . $vocabulary->get('new_revision') . "\n";
      $list .= "\n";
    }
    return $list;
  }

  /**
   * Get all the taxonomy terms.
   *
   * @return array
   *   The taxonomy terms.
   */
  public function getTaxonomyTerms($vid = NULL) {
    $terms = Term::loadMultiple();
    $termsList = [];
    foreach ($terms as $term) {
      if ($vid && $term->vid->target_id != $vid) {
        continue;
      }
      $termsList[$term->id()] = $term->label();
    }
    return $termsList;
  }

  /**
   * Get all the taxonomy terms as a string with verbose information.
   *
   * @return string
   *   The taxonomy terms as a string.
   */
  public function getVerboseTaxonomyTermsAsString($vid = NULL) {
    $terms = $this->getTaxonomyTerms($vid);
    $list = "";
    foreach ($terms as $dataName => $term) {
      // Load the term.
      $term = Term::load($dataName);

      $vocabulary = $term->vid->target_id ?? '';
      $language = $term->langcode->value ?? 'en';
      $parent = $term->parent->target_id ?? '';
      $weight = $term->weight->value ?? 0;

      // Show all the configurations.
      $list .= $term->label() . ' - dataname: ' . $dataName . "\n";
      $list .= 'Description: ' . $term->getDescription() . "\n";
      $list .= 'Vocabulary: ' . $vocabulary . "\n";
      $list .= 'Term Language: ' . $language . "\n";
      $list .= 'Parent: ' . $parent . "\n";
      $list .= 'Weight: ' . $weight . "\n";
      $list .= "\n";
    }
    return $list;
  }

}
