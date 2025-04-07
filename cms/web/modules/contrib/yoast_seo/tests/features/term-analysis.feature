@api @javascript
Feature: Taxonomy term analysis works in different circumstances

  Background:
    Given module taxonomy is enabled
    And taxonomy type:
      | vid      | name     |
      | category | Category |
    And field:
      | entity_type   | bundle   | type      | field_name | field_label | form_widget      |
      | taxonomy_term | category | yoast_seo | field_seo  | SEO         | yoast_seo_widget |

  Scenario: Widget loads on Term page
    Given I am logged in as a user with the "create terms in category,use yoast seo,create url aliases" permission

    When I am on "/admin/structure/taxonomy/manage/category/add"
    And wait for the widget to be updated

    Then I should see "No focus keyword was set for this page."

  Scenario: Widget responds to focus keyword being entered
    Given field:
      | entity_type   | bundle   | type      | field_name | field_label | form_widget      |
      | taxonomy_term | category | text_with_summary | body  | Body     | text_textarea_with_summary |
    And I am logged in as a user with the "create terms in category,use yoast seo,create url aliases" permission
    And I am on "/admin/structure/taxonomy/manage/category/add"

    When I fill in "Focus keyword" with "testing"
    And wait for the widget to be updated

    Then I should see "The focus keyword doesn't appear in the first paragraph of the copy. Make sure the topic is clear immediately."
    And I should not see "No focus keyword was set for this page."

  Scenario: Widget responds to title and body being filled in
    Given I am logged in as a user with the "create terms in category,use yoast seo,create url aliases" permission
    And I am on "/admin/structure/taxonomy/manage/category/add"
    And I fill in "Focus keyword" with "world"

    When I fill in "Name" with "Hello World"
    And I fill in "Description" with "The world is a magical place! It deserves proper preservation."
    And wait for the widget to be updated

    Then I should see "The SEO title has a nice length."
    And I should see "The meta description contains the focus keyword."
    And I should see "The focus keyword appears in the first paragraph of the copy."
