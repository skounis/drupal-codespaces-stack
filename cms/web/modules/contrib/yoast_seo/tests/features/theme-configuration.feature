@api @javascript
Feature: It's possible to configure the theme that the output is rendered in for the field
  This test relies on the presence of a special first paragraph in our theme which is not present when we use the
  default theme.

  Background:
    Given theme yoast_seo_test_theme is enabled
    And theme claro is enabled
    And config system.theme has key admin with value claro
    And config system.theme has key default with value yoast_seo_test_theme
    And module node is enabled
    And content type:
      | type    | name    |
      | article | Article |
    And field:
      | entity_type | bundle  | type              | field_name | field_label | form_widget                | field_formatter |
      | node        | article | text_with_summary | body       | Body        | text_textarea_with_summary | text_default    |

  Scenario Outline: The default theme is used when nothing is configured
    Given field:
      | entity_type | bundle  | type              | field_name | field_label | form_widget                |
      | node        | article | yoast_seo         | field_seo  | SEO         | yoast_seo_widget           |
    And config node.settings has key use_admin_theme with value <use_admin_theme>
    And I am logged in as a user with the "view the administration theme,create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"
    And I fill in "Focus keyword" with "special"

    When I fill in "Body" with "Some arbitrary text"
    And wait for the widget to be updated

    Then I should see "The focus keyword appears in the first paragraph of the copy."

  Examples:
    | use_admin_theme |
    | 1               |
    | 0               |

  Scenario: If a theme is configured then that theme is used
    Given theme olivero is enabled
    And config system.theme has key default with value olivero
    And field:
      | entity_type | bundle  | type              | field_name | field_label | form_widget                | form_widget_settings                       |
      | node        | article | yoast_seo         | field_seo  | SEO         | yoast_seo_widget           | { "render_theme": "yoast_seo_test_theme" } |
    And config node.settings has key use_admin_theme with value 1
    And I am logged in as a user with the "view the administration theme,create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"
    And I fill in "Focus keyword" with "special"

    When I fill in "Body" with "Some arbitrary text"
    And wait for the widget to be updated

    Then I should see "The focus keyword appears in the first paragraph of the copy."
