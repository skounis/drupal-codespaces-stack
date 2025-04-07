<?php

/**
 * @file
 * Builds placeholder replacement tokens for node scheduler moderation states.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function scheduler_content_moderation_integration_token_info(): array {
  $info['tokens']['node']['scheduled-moderation-publish-state'] = [
    'name' => t('Scheduled Moderation Publish State'),
    'description' => t("The published state the node will be set to."),
  ];
  $info['tokens']['node']['scheduled-moderation-unpublish-state'] = [
    'name' => t('Scheduled Moderation Unpublish State'),
    'description' => t("The unpublished state the node will be set to."),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function scheduler_content_moderation_integration_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_info */
  $moderation_info = Drupal::service('content_moderation.moderation_information');
  $language_code = isset($options['language']) ? $options['language']->language : NULL;
  $replacements = [];

  if ($type == 'node' && !empty($data['node'])) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $data['node'];
    if ($moderation_info->isModeratedEntity($node)) {
      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = $moderation_info->getWorkflowForEntity($node);
      /** @var \Drupal\content_moderation\Entity\ContentModerationStateInterface[] $states */
      $states = $workflow->getTypePlugin()->getStates();
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::service('entity.repository')->getTranslationFromContext($node, $language_code, ['operation' => 'node_tokens']);
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'scheduled-moderation-publish-state':
            if ($node->hasField('publish_state') && !$node->get('publish_state')->isEmpty()) {
              $value = $node->get('publish_state')->first()->getValue()['value'];
              if (isset($states[$value])) {
                $replacements[$original] = $states[$value]->label();
              }
            }
            break;

          case 'scheduled-moderation-unpublish-state':
            if ($node->hasField('unpublish_state') && !$node->get('unpublish_state')->isEmpty()) {
              $value = $node->get('unpublish_state')->first()->getValue()['value'];
              if (isset($states[$value])) {
                $replacements[$original] = $states[$value]->label();
              }
            }
            break;
        }
      }
    }
  }

  return $replacements;
}
