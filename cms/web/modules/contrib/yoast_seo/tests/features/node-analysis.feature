@api @javascript
Feature: Node analysis works in different circumstances

  Background:
    Given module node is enabled
    And content type:
      | type    | name    |
      | article | Article |
    And field:
      | entity_type | bundle  | type      | field_name | field_label | form_widget      |
      | node        | article | yoast_seo | field_seo  | SEO         | yoast_seo_widget |

  Scenario: Widget loads on Node page
    Given I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission

    When I am on "/node/add/article"
    And wait for the widget to be updated

    Then I should see "No focus keyword was set for this page."

  Scenario: Widget responds to focus keyword being entered
    Given field:
      | entity_type | bundle  | type      | field_name | field_label | form_widget      |
      | node        | article | text_with_summary | body  | Body     | text_textarea_with_summary |
    And I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"

    When I fill in "Focus keyword" with "testing"
    And wait for the widget to be updated

    Then I should see "The focus keyword doesn't appear in the first paragraph of the copy. Make sure the topic is clear immediately."
    And I should not see "No focus keyword was set for this page."

  Scenario: Widget responds to title and body being filled in
    Given field:
      | entity_type | bundle  | type              | field_name | field_label | form_widget                | field_formatter |
      | node        | article | text_with_summary | body       | Body        | text_textarea_with_summary | text_default    |
    And I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"
    And I fill in "Focus keyword" with "world"

    When I fill in "Title" with "Hello World"
    And I fill in "Body" with "The world is a magical place! It deserves proper preservation."
    And wait for the widget to be updated

    Then I should see "The SEO title has a nice length."
    And I should see "The meta description contains the focus keyword."
    And I should see "The focus keyword appears in the first paragraph of the copy."
