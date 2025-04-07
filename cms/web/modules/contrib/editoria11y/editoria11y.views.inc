<?php

/**
 * @file
 * Views integration.
 */

/**
 * Implements hook_views_data().
 */
function editoria11y_views_data(): array {
  return [
    'editoria11y_results' => [
      'table' => [
        'group' => t('Editoria11y'),
        'provider' => 'editoria11y',
        'base' => [
          'field' => 'id',
          'title' => t('Editoria11y - Test results'),
          'help' => t('Stores Editoria11y issue list', [], ['context' => 'problems']),
          'weight' => -10,
        ],
      ],
      'editoria11y_sync_check' => [
        'title' => t('Check sync setting'),
        'help' => t("Check if sync is enabled and show message if it isn't."),
        'area' => [
          'id' => 'editoria11y_sync_check',
        ],
      ],
      'id' => [
        'real field' => 'id',
        'title' => t('Id'),
        'help' => t('Test result'),
        'field' => [
          'id' => 'numeric',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'argument' => [
          'id' => 'numeric',
        ],
      ],
      'entity_id' => [
        'real field' => 'entity_id',
        'title' => t('Entity ID'),
        'help' => t('NID/TID'),
        'field' => [
          'id' => 'numeric',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'argument' => [
          'id' => 'numeric',
        ],
      ],
      'entity_type' => [
        'real field' => 'entity_type',
        'title' => t('Entity type'),
        'help' => t('The entity type; "route" if no type found.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getEntityTypeOptions',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'route_name' => [
        'real field' => 'route_name',
        'title' => t('Route name'),
        'help' => t('Route name for page.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'page_path' => [
        'real field' => 'page_path',
        'title' => t('Page path'),
        'help' => t('Internal, relative page path.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'page_language' => [
        'real field' => 'page_language',
        'title' => t('Page language'),
        'help' => t('Active translation.'),
        'field' => [
          'id' => 'language',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'language',
        ],
        'argument' => [
          'id' => 'language',
        ],
      ],
      'page_url' => [
        'real field' => 'page_url',
        'title' => t('Page url'),
        'help' => t('Raw URL.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'page_title' => [
        'real field' => 'page_title',
        'title' => t('Page title'),
        'help' => t('The name of the route where this was last seen.'),
        'field' => [
          'id' => 'editoria11y_page_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'page_result_count' => [
        'real field' => 'page_result_count',
        'title' => t('Page result count'),
        'help' => t('The total number of issues on this page.', [], ['context' => 'problems']),
        'field' => [
          'id' => 'editoria11y_issues_by_page_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'argument' => [
          'id' => 'numeric',
        ],
      ],
      'created' => [
        'real field' => 'created',
        'title' => t('Created'),
        'help' => t('The Unix timestamp of the first time this record was flagged.'),
        'field' => [
          'id' => 'date',
        ],
        'sort' => [
          'id' => 'date',
        ],
        'filter' => [
          'id' => 'date',
        ],
      ],
      'updated' => [
        'real field' => 'updated',
        'title' => t('Updated'),
        'help' => t('The Unix timestamp of the last update.'),
        'field' => [
          'id' => 'date',
        ],
        'sort' => [
          'id' => 'date',
        ],
        'filter' => [
          'id' => 'date',
        ],
      ],
      'result_name' => [
        'real field' => 'result_name',
        'title' => t('Result name'),
        'help' => t('The name of the test as reported by Editoria11y JS'),
        'field' => [
          'id' => 'editoria11y_pages_by_issue_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getResultNameOptions',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'result_name_count' => [
        'real field' => 'result_name_count',
        'title' => t('Result name count'),
        'help' => t('The number of hits for this test type on this page'),
        'field' => [
          'id' => 'editoria11y_issues_by_page_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'argument' => [
          'id' => 'numeric',
        ],
      ],
    ],
    'editoria11y_dismissals' => [
      'table' => [
        'group' => t('Editoria11y'),
        'provider' => 'editoria11y',
        'base' => [
          'field' => 'id',
          'title' => t('Editoria11y - Dismissals'),
          'help' => t('Stores Editoria11y warnings and dismissals'),
          'weight' => -10,
        ],
      ],
      'editoria11y_sync_check' => [
        'title' => t('Check sync setting'),
        'help' => t("Check if sync is enabled and show message if it isn't."),
        'area' => [
          'id' => 'editoria11y_sync_check',
        ],
      ],
      'id' => [
        'real field' => 'id',
        'title' => t('Id'),
        'help' => 'Element affected',
        'field' => [
          'id' => 'numeric',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'standard',
        ],
      ],
      'uid' => [
        'real field' => 'uid',
        'title' => t('Drupal User ID'),
        'help' => t('The user linked to this dismissal.'),
        'relationship' => [
          'base' => 'users_field_data',
          'base field' => 'uid',
          'id' => 'standard',
          'label' => t('Linked Drupal user'),
        ],
        'field' => [
          'id' => 'numeric',
        ],
        'filter' => [
          'id' => 'user_name',
        ],
        'sort' => [
          'id' => 'standard',
        ],
      ],
      'element_id' => [
        'real field' => 'element_id',
        'title' => t('Element id'),
        'help' => t('Code sample to identify the flagged element.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'entity_type' => [
        'real field' => 'entity_type',
        'title' => t('Entity type'),
        'help' => t('The entity type; "route" if no type found.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getEntityTypeOptions',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],

      'entity_id' => [
        'real field' => 'entity_id',
        'title' => t('Entity ID'),
        'help' => t('NID/TID'),
        'field' => [
          'id' => 'numeric',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'argument' => [
          'id' => 'numeric',
        ],
      ],
      'page_path' => [
        'real field' => 'page_path',
        'title' => t('Page path'),
        'help' => t('The name of the route where this was last seen.'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'page_language' => [
        'real field' => 'page_language',
        'title' => t('Page language'),
        'help' => t('Active translation.'),
        'field' => [
          'id' => 'language',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'language',
        ],
        'argument' => [
          'id' => 'language',
        ],
      ],
      'page_title' => [
        'real field' => 'page_title',
        'title' => t('Page title'),
        'help' => t('The name of the route where this was last seen.'),
        'field' => [
          'id' => 'editoria11y_page_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'route_name' => [
        'real field' => 'route_name',
        'title' => t('Route name'),
        'help' => t('The entity type of the route where this was dismissed'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'created' => [
        'real field' => 'created',
        'title' => t('Created'),
        'help' => t('The Unix timestamp of the first time this record was flagged.'),
        'field' => [
          'id' => 'date',
        ],
        'sort' => [
          'id' => 'date',
        ],
        'filter' => [
          'id' => 'date',
        ],
      ],
      'updated' => [
        'real field' => 'updated',
        'title' => t('Updated'),
        'help' => t('The Unix timestamp of the last update.'),
        'field' => [
          'id' => 'date',
        ],
        'sort' => [
          'id' => 'date',
        ],
        'filter' => [
          'id' => 'date',
        ],
      ],
      'result_name' => [
        'real field' => 'result_name',
        'title' => t('Result name'),
        'help' => t('The name of the test as reported by Editoria11y JS'),
        'field' => [
          'id' => 'editoria11y_pages_by_issue_link',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getResultNameOptions',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'result_key' => [
        'real field' => 'result_key',
        'title' => t('Result key'),
        'help' => t('The name of the test as reported by editoria11y JS'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'string',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'dismissal_status' => [
        'real field' => 'dismissal_status',
        'title' => t('Dismissal status'),
        'help' => t('The type of dismissal (e.g., OK, Ignore)'),
        'field' => [
          'id' => 'standard',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getDismissalOptions',
        ],
        'argument' => [
          'id' => 'string',
        ],
      ],
      'stale' => [
        'real field' => 'stale',
        'title' => t('Stale'),
        'help' => t('Not seen on most recent page check'),
        'field' => [
          'id' => 'boolean',
        ],
        'filter' => [
          'id' => 'in_operator',
          'options callback' => '\Drupal\editoria11y\Dashboard::getStaleOptions',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'argument' => [
          'id' => 'boolean',
        ],
      ],
    ],
  ];
}
