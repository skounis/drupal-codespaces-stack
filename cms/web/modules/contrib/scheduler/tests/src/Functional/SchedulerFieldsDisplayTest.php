<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Url;

/**
 * Tests the display of date entry fields and form elements.
 *
 * @todo Extend these tests to cover form display processing when entity
 * type is enabled/disabled.
 * @see https://www.drupal.org/project/scheduler/issues/3320341
 *
 * @group scheduler
 */
class SchedulerFieldsDisplayTest extends SchedulerBrowserTestBase {

  /**
   * Additional core module field_ui is required for testManageFormDisplay.
   *
   * @var array
   */
  protected static $modules = ['field_ui'];

  /**
   * Tests the Scheduler options display on entity type add and edit forms.
   *
   * This test covers hook_form_alter() and _scheduler_entity_type_form_alter().
   *
   * @dataProvider dataEntityTypeForm
   */
  public function testEntityTypeForm($entityTypeId, $bundle, $operation) {
    $this->drupalLogin($this->adminUser);

    if ($operation == 'add first') {
      // Delete all the entity types for this bundle, to check that 'add'
      // works when it would be the first type being added.
      $this->entityTypeObject($entityTypeId)->delete();
      $this->entityTypeObject($entityTypeId, 'non-enabled')->delete();
    }

    $url = $this->adminUrl($operation == 'edit' ? 'bundle_edit' : 'bundle_add', $entityTypeId, $bundle);
    $this->drupalGet($url);
    $this->assertSession()->fieldExists('edit-scheduler-publish-enable');
    $this->assertSession()->fieldExists('edit-scheduler-unpublish-enable');
  }

  /**
   * Provides data for testEntityTypeForm.
   *
   * @return array
   *   Each row has values: [entity type id, bundle id, operation].
   */
  public static function dataEntityTypeForm() {
    $types = self::dataStandardEntityTypes();
    $data = [];
    foreach ($types as $key => $values) {
      $data["$key-1"] = array_merge($values, ['add first']);
      $data["$key-2"] = array_merge($values, ['add']);
      $data["$key-3"] = array_merge($values, ['edit']);
    }
    return $data;
  }

  /**
   * Tests the scheduler fields on the admin entity type form display tab.
   *
   * This test covers scheduler_entity_extra_field_info().
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testManageFormDisplay($entityTypeId, $bundle) {
    // Give adminUser the permissions to use the field_ui 'manage form display'
    // tab for the entity type being tested.
    $this->addPermissionsToUser($this->adminUser, ["administer {$entityTypeId} form display"]);
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    // Check that the weight input field is displayed when the entity bundle is
    // enabled for scheduling. This field still exists even with tabledrag on.
    $form_display_url = Url::fromRoute("entity.entity_form_display.{$entityTypeId}.default", [$entityType->getEntityTypeId() => $bundle]);
    $this->drupalGet($form_display_url);
    $this->assertSession()->fieldExists('edit-fields-scheduler-settings-weight');

    // Check that the weight input field is not displayed when the entity bundle
    // is not enabled for scheduling.
    $this->entityTypeObject($entityTypeId, $bundle)
      ->setThirdPartySetting('scheduler', 'publish_enable', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', FALSE)->save();
    $this->drupalGet($form_display_url);
    $this->assertSession()->pageTextContains('Manage form display');
    $this->assertSession()->FieldNotExists('edit-fields-scheduler-settings-weight');
  }

  /**
   * Tests date input is displayed as vertical tab or an expandable fieldset.
   *
   * This test covers hook_form_alter() and _scheduler_entity_form_alter().
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testVerticalTabOrFieldset($entityTypeId, $bundle) {
    $this->drupalLogin($this->adminUser);
    $entityType = $this->entityTypeObject($entityTypeId, $bundle);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // For rendering of vertical tabs, node and media entity forms have a div
    // with class 'js-form-type-vertical-tabs'. However, the Commerce Product
    // module does things differently and does not have this class, but instead
    // has a class 'layout-region-product-secondary' (for vertical tabs) and
    // 'layout-region-product-main' if in the main form not in vertical tabs. So
    // to cover all entity types we can check for either of these classes as an
    // ancestor of the 'edit-scheduler-settings' section.
    $vertical_tab_xpath = '//div[contains(@class, "form-type-vertical-tabs") or contains(@class, "-secondary")]//details[@id = "edit-scheduler-settings"]';

    // The 'open' and 'closed' xpath searches do apply to vertical tabs, even if
    // the theme does not actually make use of it (such as in Bartik and Stark).
    $details_open_xpath = '//details[@id = "edit-scheduler-settings" and @open = "open"]';
    $details_closed_xpath = '//details[@id = "edit-scheduler-settings" and not(@open = "open")]';

    // Check that the dates are shown in a vertical tab by default. The taxonomy
    // term form does not have a vertical tab section, so cannot check for this.
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    if ($check_vertical_tab = ($entityTypeId != 'taxonomy_term')) {
      $assert->elementExists('xpath', $vertical_tab_xpath);
    }
    $assert->elementExists('xpath', $details_closed_xpath);

    // Check that the dates are shown as a fieldset when configured to do so,
    // and that fieldset is collapsed by default.
    $entityType->setThirdPartySetting('scheduler', 'fields_display_mode', 'fieldset')->save();
    $this->drupalGet($add_url);
    $assert->elementNotExists('xpath', $vertical_tab_xpath);
    $assert->elementExists('xpath', $details_closed_xpath);

    // Check that the fieldset is expanded if either of the scheduling dates
    // are required.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', TRUE)->save();
    $this->drupalGet($add_url);
    $assert->elementExists('xpath', $details_open_xpath);

    $entityType->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', TRUE)->save();
    $this->drupalGet($add_url);
    $assert->elementExists('xpath', $details_open_xpath);

    // Check that the fieldset is expanded if the 'always' option is set.
    $entityType->setThirdPartySetting('scheduler', 'publish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'unpublish_required', FALSE)
      ->setThirdPartySetting('scheduler', 'expand_fieldset', 'always')->save();
    $this->drupalGet($add_url);
    $assert->elementExists('xpath', $details_open_xpath);

    // Check that the fieldset is expanded if the entity already has a
    // publish-on date. This requires editing an existing scheduled entity.
    $entityType->setThirdPartySetting('scheduler', 'expand_fieldset', 'when_required')->save();
    $options = [
      'title' => 'Contains Publish-on date ' . $this->randomMachineName(10),
      'publish_on' => strtotime('+1 day'),
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->elementExists('xpath', $details_open_xpath);

    // Repeat the check with a timestamp value of zero. This is a valid date
    // so the fieldset should be opened. It will not be used much on real sites
    // but can occur when testing Rules which fail to set the date correctly and
    // we get zero. Debugging Rules is easier if the fieldset opens as expected.
    $options = [
      'title' => 'Contains Publish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'publish_on' => 0,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->elementExists('xpath', $details_open_xpath);

    // Check that the fieldset is expanded if there is an unpublish-on date.
    $options = [
      'title' => 'Contains Unpublish-on date ' . $this->randomMachineName(10),
      'unpublish_on' => strtotime('+1 day'),
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->elementExists('xpath', $details_open_xpath);

    // Repeat with a timestamp value of zero.
    $options = [
      'title' => 'Contains Unpublish-on date with timestamp value zero - ' . $this->randomMachineName(10),
      'unpublish_on' => 0,
    ];
    $entity = $this->createEntity($entityTypeId, $bundle, $options);
    $this->drupalGet($entity->toUrl('edit-form'));
    $assert->elementExists('xpath', $details_open_xpath);

    // Check that the display reverts to a vertical tab again when specifically
    // configured to do so.
    $entityType->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab')->save();
    $this->drupalGet($entity->toUrl('edit-form'));
    if ($check_vertical_tab) {
      $assert->elementExists('xpath', $vertical_tab_xpath);
    }
    $assert->elementExists('xpath', $details_open_xpath);
  }

  /**
   * Tests the edit form when scheduler fields have been disabled.
   *
   * This test covers _scheduler_entity_form_alter().
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testDisabledFields($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // 1. Set the publish_on field to 'hidden' in the entity edit form.
    $formDisplay = $this->container->get('entity_display.repository')->getFormDisplay($entityTypeId, $bundle);
    $publish_on_component = $formDisplay->getComponent('publish_on');
    $formDisplay->removeComponent('publish_on')->save();

    // Check that the scheduler details element is shown and that the
    // unpublish_on field is shown, but the publish_on field is not shown.
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings"]');
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldExists('unpublish_on[0][value][date]');

    // 2. Set publish_on to be displayed but hide the unpublish_on field.
    $formDisplay->setComponent('publish_on', $publish_on_component)
      ->removeComponent('unpublish_on')->save();

    // Check that the scheduler details element is shown and that the
    // publish_on field is shown, but the unpublish_on field is not shown.
    $this->drupalGet($add_url);
    $assert->elementExists('xpath', '//details[@id = "edit-scheduler-settings"]');
    $this->assertSession()->FieldExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');

    // 3. Set both fields to be hidden.
    $formDisplay->removeComponent('publish_on')->save();

    // Check that the scheduler details element is not shown when both of the
    // date fields are set to be hidden.
    $this->drupalGet($add_url);
    $assert->elementNotExists('xpath', '//details[@id = "edit-scheduler-settings"]');
    $this->assertSession()->FieldNotExists('publish_on[0][value][date]');
    $this->assertSession()->FieldNotExists('unpublish_on[0][value][date]');
  }

  /**
   * Test the option to hide the seconds on the time input fields.
   *
   * @dataProvider dataStandardEntityTypes
   */
  public function testHideSeconds($entityTypeId, $bundle) {
    $this->drupalLogin($this->schedulerUser);
    $config = $this->config('scheduler.settings');
    $titleField = $this->titleField($entityTypeId);

    // Check that the default is to show the seconds on the input fields.
    $add_url = $this->entityAddUrl($entityTypeId, $bundle);
    $this->drupalGet($add_url);
    $publish_time_field = $this->xpath('//input[@id="edit-publish-on-0-value-time"]');
    $unpublish_time_field = $this->xpath('//input[@id="edit-unpublish-on-0-value-time"]');
    $this->assertEquals(1, $publish_time_field[0]->getAttribute('step'), 'The input time step for publish-on is 1, so the seconds will be visible and usable.');
    $this->assertEquals(1, $unpublish_time_field[0]->getAttribute('step'), 'The input time step for unpublish-on is 1, so the seconds will be visible and usable.');

    // Set the config option to hide the seconds and thus set the input fields
    // to the granularity of one minute.
    $config->set('hide_seconds', TRUE)->save();

    // Go to the 'add' url and check the input fields.
    $this->drupalGet($add_url);
    $publish_time_field = $this->xpath('//input[@id="edit-publish-on-0-value-time"]');
    $unpublish_time_field = $this->xpath('//input[@id="edit-unpublish-on-0-value-time"]');
    $this->assertEquals(60, $publish_time_field[0]->getAttribute('step'), 'The input time step for publish-on is 60, so the seconds will be hidden and not usable.');
    $this->assertEquals(60, $unpublish_time_field[0]->getAttribute('step'), 'The input time step for unpublish-on is 60, so the seconds will be hidden and not usable.');
    // @todo How can we check that the seconds element is not shown?

    // Save with both dates entered, including seconds in the times.
    $edit = [
      "{$titleField}[0][value]" => 'Hide the seconds',
      'publish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'publish_on[0][value][time]' => '01:02:03',
      'unpublish_on[0][value][date]' => date('Y-m-d', strtotime('+1 day', $this->requestTime)),
      'unpublish_on[0][value][time]' => '04:05:06',
    ];
    $this->submitForm($edit, 'Save');
    $entity = $this->getEntityByTitle($entityTypeId, 'Hide the seconds');

    // Edit and check that the seconds have been set to zero.
    $this->drupalGet($entity->toUrl('edit-form'));
    $this->assertSession()->FieldValueEquals('publish_on[0][value][time]', '01:02:00');
    $this->assertSession()->FieldValueEquals('unpublish_on[0][value][time]', '04:05:00');

  }

}
