@api @javascript
Feature: The interaction between Drupal's output and the library works correctly
  This is tested with nodes but should work the same for any entity type.

  Background:
    Given module node is enabled
    And content type:
      | type    | name    |
      | article | Article |
    And field:
      | entity_type | bundle  | type              | field_name | field_label | form_widget                | field_formatter |
      | node        | article | yoast_seo         | field_seo  | SEO         | yoast_seo_widget           |                 |
      | node        | article | text_with_summary | body       | Body        | text_textarea_with_summary | text_default    |

  # We rely on Drupal's automatic paragraph splitting and we match against the processed output of Drupal.
  # The system should ignore whitespace in the resulting HTML which is slightly different from how the Wordpress
  # integration would work.
  Scenario Outline: Paragraphs in text are properly processed
    Given I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"
    And I fill in "Focus keyword" with "<keyword>"

    When I fill in "Body" with:
      """
      The world is a magical place

      And this keyword is in the second paragraph
      """
    And wait for the widget to be updated

    Then I should see "The meta description contains the focus keyword."
    And I should see "<feedback>"

  Examples:
    | keyword | feedback                                                                                                      |
    | world   | The focus keyword appears in the first paragraph of the copy.                                                 |
    | keyword | The focus keyword doesn't appear in the first paragraph of the copy. Make sure the topic is clear immediately.|
