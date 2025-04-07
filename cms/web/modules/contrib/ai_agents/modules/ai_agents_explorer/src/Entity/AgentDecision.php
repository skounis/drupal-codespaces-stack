<?php

declare(strict_types=1);

namespace Drupal\ai_agents_explorer\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ai_agents_explorer\AgentDecisionInterface;

/**
 * Defines the agent decision entity class.
 *
 * @ContentEntityType(
 *   id = "ai_agent_decision",
 *   label = @Translation("Agent Decision"),
 *   label_collection = @Translation("Agent Decisions"),
 *   label_singular = @Translation("agent decision"),
 *   label_plural = @Translation("agent decisions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count agent decisions",
 *     plural = "@count agent decisions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_agents_explorer\AgentDecisionListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ai_agents_explorer\Form\AgentDecisionForm",
 *       "edit" = "Drupal\ai_agents_explorer\Form\AgentDecisionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ai_agents_explorer\Routing\AgentDecisionHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "ai_agent_decision",
 *   admin_permission = "administer ai_agent_decision",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/ai-agent-decision",
 *     "add-form" = "/ai-agent-decision/add",
 *     "canonical" = "/ai-agent-decision/{ai_agent_decision}",
 *     "edit-form" = "/ai-agent-decision/{ai_agent_decision}",
 *     "delete-form" = "/ai-agent-decision/{ai_agent_decision}/delete",
 *     "delete-multiple-form" = "/admin/content/ai-agent-decision/delete-multiple",
 *   },
 * )
 */
final class AgentDecision extends ContentEntityBase implements AgentDecisionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The label of the Agent Decision entity.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['runner_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Agent Runner ID'))
      ->setDescription(t('The ID of the agent runner.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['microtime'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Microtime'))
      ->setDescription(t('The microtime of the decision.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['action'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Action'))
      ->setDescription(t('The action taken.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['log_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Log Status'))
      ->setRequired(TRUE)
      ->setDescription(t('The status of the log.'))
      ->setSettings([
        'default_value' => 'notice',
        'allowed_values' => [
          'notice' => 'Notice',
          'warning' => 'Warning',
          'error' => 'Error',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'list_string',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['prompt_used'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Prompt Used'))
      ->setDescription(t('The prompt used for the decision.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['question'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Question'))
      ->setDescription(t('The question to the agent.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['response_given'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Response Given'))
      ->setDescription(t('The response given by the agent.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['detailed_output'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Detailed Output'))
      ->setDescription(t('The detailed output of the decision.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
