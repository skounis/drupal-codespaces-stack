<?php

namespace Drupal\Tests\scheduler\Functional;

/**
 * Tests the options for scheduling dates to be required during add/edit.
 *
 * @group scheduler
 */
class SchedulerRequiredTest extends SchedulerBrowserTestBase {

  /**
   * Tests creating and editing nodes with required scheduling enabled.
   *
   * @dataProvider dataRequiredScheduling
   */
  public function testRequiredScheduling($id, $publish_required, $unpublish_required, $operation, $scheduled, $status, $publish_expected, $unpublish_expected, $message) {

    $this->drupalLogin($this->schedulerUser);

    $fields = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', $this->type);

    // Set required (un)publishing as stipulated by the test case.
    $this->nodetype->setThirdPartySetting('scheduler', 'publish_required', $publish_required)
      ->setThirdPartySetting('scheduler', 'unpublish_required', $unpublish_required)
      ->save();

    // To assist viewing and analyzing the generated test result pages create a
    // text string showing all the test case parameters.
    $title_data = ['id = ' . $id,
      $publish_required ? 'Publishing required' : '',
      $unpublish_required ? 'Unpublishing required' : '',
      'on ' . $operation,
      $status ? 'published' : 'unpublished',
      $scheduled ? 'scheduled' : 'not scheduled',
    ];
    // Remove any empty items.
    $title_data = array_filter($title_data);
    $title = implode(', ', $title_data);

    // If the scenario requires editing a node, we need to create one first.
    if ($operation == 'edit') {
      // Note: The key names in the $options parameter for drupalCreateNode()
      // are the plain field names i.e. 'title' not title[0][value].
      $options = [
        'title' => $title,
        'type' => $this->type,
        'status' => $status,
        'publish_on' => $scheduled ? strtotime('+1 day') : NULL,
        'body' => $message,
      ];
      $node = $this->drupalCreateNode($options);
      // Define the path and button to use for editing the node.
      $path = 'node/' . $node->id() . '/edit';
    }
    else {
      // Set the default status, used when testing creation of the new node.
      $fields['status']->getConfig($this->type)
        ->setDefaultValue($status)
        ->save();
      // Define the path and button to use for creating the node.
      $path = 'node/add/' . $this->type;
    }

    // Make sure that both date fields are empty so we can check if they throw
    // validation errors when the fields are required.
    $values = [
      'title[0][value]' => $title,
      'publish_on[0][value][date]' => '',
      'publish_on[0][value][time]' => '',
      'unpublish_on[0][value][date]' => '',
      'unpublish_on[0][value][time]' => '',
    ];
    // Add or edit the node.
    $this->drupalGet($path);
    $this->submitForm($values, 'Save');

    // Check for the expected result.
    if ($publish_expected) {
      $string = sprintf('The %s date is required.', ucfirst('publish') . ' on');
      $this->assertSession()->pageTextContains($string);
    }
    if ($unpublish_expected) {
      $string = sprintf('The %s date is required.', ucfirst('unpublish') . ' on');
      $this->assertSession()->pageTextContains($string);
    }
    if (!$publish_expected && !$unpublish_expected) {
      $string = sprintf('%s %s has been %s.', $this->typeName, $title, ($operation == 'add' ? 'created' : 'updated'));
      $this->assertSession()->pageTextContains($string);
    }
  }

  /**
   * Provides data for testRequiredScheduling().
   *
   * @return array
   *   id                 - a sequential id to help in identifying test output
   *   publish_required   - (bool) whether the publish_on field is required
   *   unpublish_required - (bool) whether the unpublish_on field is required
   *   operation          - what is being done to the node, 'add' or 'edit'
   *   scheduled          - (bool) the node is already scheduled for publishing
   *   status             - (bool) the current published status of the node
   *   publish_expected   - (bool) will this scenario produced a 'publish on
   *                        required' error message
   *   unpublish_expected -  (bool) will this scenario produced a 'unpublish on
   *                        required' error message
   *   message            - Descriptive text used in the body of the node
   */
  public static function dataRequiredScheduling() {

    $data = [
      // The numbering used below matches the test cases described in
      // http://drupal.org/node/1198788#comment-7816119

      // Check the default case when neither date should be required.
      '#node-0' => [
        'id' => 0,
        'publish_required' => FALSE,
        'unpublish_required' => FALSE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => FALSE,
        'unpublish_expected' => FALSE,
        'message' => 'By default when a new node is created, the publish on and unpublish on dates are not required.',
      ],

      // A. Test scenarios that require scheduled publishing.
      // When creating a new unpublished node it is required to enter a
      // publication date.
      '#node-1' => [
        'id' => 1,
        'publish_required' => TRUE,
        'unpublish_required' => FALSE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => TRUE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled publishing is required and a new unpublished node is created, entering a date in the publish on field is required.',
      ],

      // When creating a new published node it is required to enter a
      // publication date. The node will be unpublished on form submit.
      '#node-2' => [
        'id' => 2,
        'publish_required' => TRUE,
        'unpublish_required' => FALSE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => TRUE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled publishing is required and a new published node is created, entering a date in the publish on field is required.',
      ],

      // When editing a published node it is not needed to enter a publication
      // date since the node is already published.
      '#node-3' => [
        'id' => 3,
        'publish_required' => TRUE,
        'unpublish_required' => FALSE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => FALSE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled publishing is required and an existing published, unscheduled node is edited, entering a date in the publish on field is not required.',
      ],

      // When editing an unpublished node that is scheduled for publication it
      // is required to enter a publication date.
      '#node-4' => [
        'id' => 4,
        'publish_required' => TRUE,
        'unpublish_required' => FALSE,
        'operation' => 'edit',
        'scheduled' => TRUE,
        'status' => FALSE,
        'publish_expected' => TRUE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled publishing is required and an existing unpublished, scheduled node is edited, entering a date in the publish on field is required.',
      ],

      // When editing an unpublished node that is not scheduled for publication
      // it is not required to enter a publication date since this means that
      // the node has already gone through a publication > unpublication cycle.
      '#node-5' => [
        'id' => 5,
        'publish_required' => TRUE,
        'unpublish_required' => FALSE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => FALSE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled publishing is required and an existing unpublished, unscheduled node is edited, entering a date in the publish on field is not required.',
      ],

      // B. Test scenarios that require scheduled unpublishing.

      // When creating a new unpublished node it is required to enter an
      // unpublication date since it is to be expected that the node will be
      // published at some point and should subsequently be unpublished.
      '#node-6' => [
        'id' => 6,
        'publish_required' => FALSE,
        'unpublish_required' => TRUE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => FALSE,
        'unpublish_expected' => TRUE,
        'message' => 'When scheduled unpublishing is required and a new unpublished node is created, entering a date in the unpublish on field is required.',
      ],

      // When creating a new published node it is required to enter an
      // unpublication date.
      '#node-7' => [
        'id' => 7,
        'publish_required' => FALSE,
        'unpublish_required' => TRUE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => FALSE,
        'unpublish_expected' => TRUE,
        'message' => 'When scheduled unpublishing is required and a new published node is created, entering a date in the unpublish on field is required.',
      ],

      // When editing a published node it is required to enter an unpublication
      // date.
      '#node-8' => [
        'id' => 8,
        'publish_required' => FALSE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => FALSE,
        'unpublish_expected' => TRUE,
        'message' => 'When scheduled unpublishing is required and an existing published, unscheduled node is edited, entering a date in the unpublish on field is required.',
      ],

      // When editing an unpublished node that is scheduled for publication it
      // it is required to enter an unpublication date.
      '#node-9' => [
        'id' => 9,
        'publish_required' => FALSE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => TRUE,
        'status' => FALSE,
        'publish_expected' => FALSE,
        'unpublish_expected' => TRUE,
        'message' => 'When scheduled unpublishing is required and an existing unpublished, scheduled node is edited, entering a date in the unpublish on field is required.',
      ],

      // When editing an unpublished node that is not scheduled for publication
      // it is not required to enter an unpublication date since this means that
      // the node has already gone through a publication - unpublication cycle.
      '#node-10' => [
        'id' => 10,
        'publish_required' => FALSE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => FALSE,
        'unpublish_expected' => FALSE,
        'message' => 'When scheduled unpublishing is required and an existing unpublished, unscheduled node is edited, entering a date in the unpublish on field is not required.',
      ],

      // C. Test scenarios that require both publishing and unpublishing.

      // This section is an amalgamation of the values in the sections A and B
      // to check that the settings do not interfere with each other.
      '#node-11' => [
        'id' => 11,
        'publish_required' => TRUE,
        'unpublish_required' => TRUE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => TRUE,
        'unpublish_expected' => TRUE,
        'message' => 'When both scheduled publishing and unpublishing are required and a new unpublished node is created, entering a date in both the publish and unpublish on fields is required.',
      ],

      '#node-12' => [
        'id' => 12,
        'publish_required' => TRUE,
        'unpublish_required' => TRUE,
        'operation' => 'add',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => TRUE,
        'unpublish_expected' => TRUE,
        'message' => 'When both scheduled publishing and unpublishing are required and a new published node is created, entering a date in both the publish and unpublish on fields is required.',
      ],

      '#node-13' => [
        'id' => 13,
        'publish_required' => TRUE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => TRUE,
        'publish_expected' => FALSE,
        'unpublish_expected' => TRUE,
        'message' => 'When both scheduled publishing and unpublishing are required and an existing published, unscheduled node is edited, entering a date in the unpublish on field is required, but a publish date is not required.',
      ],

      '#node-14' => [
        'id' => 14,
        'publish_required' => TRUE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => TRUE,
        'status' => FALSE,
        'publish_expected' => TRUE,
        'unpublish_expected' => TRUE,
        'message' => 'When both scheduled publishing and unpublishing are required and an existing unpublished, scheduled node is edited, entering a date in both the publish and unpublish on fields is required.',
      ],

      '#node-15' => [
        'id' => 15,
        'publish_required' => TRUE,
        'unpublish_required' => TRUE,
        'operation' => 'edit',
        'scheduled' => FALSE,
        'status' => FALSE,
        'publish_expected' => FALSE,
        'unpublish_expected' => FALSE,
        'message' => 'When both scheduled publishing and unpublishing are required and an existing unpublished, unscheduled node is edited, entering a date in the publish or unpublish on fields is not required.',
      ],

    ];

    return $data;
  }

}
