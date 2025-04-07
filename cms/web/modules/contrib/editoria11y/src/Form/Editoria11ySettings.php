<?php

namespace Drupal\editoria11y\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class to define all settings of the module.
 */
class Editoria11ySettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'editoria11y_form_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'editoria11y.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('editoria11y.settings');
    $permissions = Url::fromRoute('user.admin_permissions');
    $linkToPermissions = Link::fromTextAndUrl(t("user roles that edit content"), $permissions)->toString();
    $dashboard = Url::fromRoute('editoria11y.reports_dashboard');
    $linkToDashboard = Link::fromTextAndUrl(t("To view the dashboard"), $dashboard)->toString();

    $form['getting_started'] = [
      '#markup' => '<h2>' .
      $this->t("Getting started") .
      '</h2><ol></ol>',
    ];

    $form['setup'] = [
      '#type' => 'fieldset',
      '#title' => t('Basic Configuration'),
      '#markup' => '<p>' . $this->t("Make sure") . ' ' .
      $linkToPermissions . ' ' .
      $this->t('have the "View Editoria11y checker" permission. Assign "mark OK" as appropriate.') . ' ' .
      $linkToDashboard . $this->t(', users need "Manage Editoria11y results."') . '</p>',
    ];

    $form['setup']['ed11y_theme'] = [
      '#title' => $this->t("Theme"),
      '#type' => 'select',
      '#options' => [
        'sleekTheme' => $this->t('Sleek'),
        'lightTheme' => $this->t('Classic'),
        'darkTheme' => $this->t('Dark'),
      ],
      '#default_value' => $config->get('ed11y_theme'),
    ];

    $form['setup']['assertiveness'] = [
      '#title' => $this->t("Show tips automatically when issues are detected"),
      '#type' => 'radios',
      '#options' => [
        'assertive' => $this->t('Always'),
        'smart' => $this->t('On first detection'),
        'polite' => $this->t('Never'),
      ],
      '#default_value' => $config->get('assertiveness'),
    ];

    $form['setup']['content_root'] = [
      '#title' => $this->t("Check content in these containers"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('To limit checks to user-editable containers, provide a list of <a target="_blank" href="https://developer.mozilla.org/en-US/docs/Learn/CSS/Building_blocks/Selectors">CSS selectors</a>.<br>E.g.: <code><em>main, #footer-editable-content</em></code><br>Default: <code>main</code>, falling back to <code>body</code>. If elements are specified here and none are found on the page, Editoria11y will not find anything!'),
      '#default_value' => $config->get('content_root'),
    ];

    $form['setup']['ignore_elements'] = [
      '#title' => $this->t("Skip over these elements"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Provide a comma-separated list of selectors for elements to ignore. These should target specific elements (use an asterisk to indicate "all children of this element"). <br>E.g.: <code><em>#sidebar-menu a, .card img, .slide [aria-hidden="true"], .feed *</em></code>.'),
      '#default_value' => $config->get('ignore_elements'),
    ];

    $form['troubleshooting'] = [
      '#type' => 'details',
      '#title' => t('Troubleshooting'),
      '#markup' => '<ul><li>' .
      $this->t("Review each setting under Basic Configuration. Many sites need to adjust what parts of the page are scanned") .
      '</li><li>' . $this->t("Remember that results only sync to the dashboard when viewing nodes. Results shown while editing or viewing previews or revisions will not sync.") .
      '</li><li>' .
      $this->t('If the checker <strong>toggle</strong> does not appear: make sure a z-indexed or overflow-hidden element in your front-end theme is not hiding or covering the <code><em>ed11y-element-panel</em></code> container, make sure that any custom selectors in the "Disable the scanner if these elements are detected" field are not present, and make sure that no JavaScript errors are appearing in your <a href="https://developer.mozilla.org/en-US/docs/Tools/Browser_Console"> browser console</a>') .
        '.</li><li>' . // phpcs:ignore
      $this->t("If the checker toggle is present but <strong>not detecting</strong> errors, or missing errors that should be flagged: check that your inclusions & exclusion settings below are not missing or ignoring the elements. It is not uncommon for homepages or views to insert editable content outside the <code><em>main</em></code> element.") .
      '</li>' . '<li>' . $this->t('Check the <a href="https://www.drupal.org/project/editoria11y">Module Overview</a> | <a href="https://editoria11y.princeton.edu/demo/">Working Demo</a> | <a href="https://www.drupal.org/project/issues/editoria11y?categories=All">Issue Queue</a> | <a href="https://editoria11y.princeton.edu/configuration/">Library Configuration Guide') . '</a></li>' .
      '</ol>',
    ];

    $form['adv'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced configuration'),
    ];

    $form['adv']['tests'] = [
      '#type' => 'details',
      '#title' => t('Modify tests'),
    ];

    $form['adv']['tests']['ignore_tests'] = [
      '#title' => $this->t("Checks"),
      '#type' => 'checkboxes',
      '#options' => [
        'altMeaningless' => $this->t('Alt text is meaningless'),
        'tableContainsContentHeading' => $this->t('Content heading inside a table'),
        'tableEmptyHeaderCell' => $this->t('Empty table header cell'),
        'headingEmpty' => $this->t('Heading tag without any text'),
        'altMissing' => $this->t('Image has no alternative text attribute'),
        'altDeadspace' => $this->t("Image's text alternative is unpronounceable"),
        'altEmptyLinked' => $this->t('Linked Image has no alt text'),
        'altURL' => $this->t("Linked image's text alternative is a URL"),
        'linkNoText' => $this->t('Link with no accessible text'),
        'tableNoHeaderCells' => $this->t('Table has no header cells'),
        'altNull' => $this->t('Manual check: image has no alt text'),
        'linkNewWindow' => $this->t('Manual check: is opening a new window expected?'),
        'blockquoteIsShort' => $this->t('Manual check: is this a blockquote?'),
        'embedAudio' => $this->t('Manual check: is an accurate transcript provided?'),
        'embedTwitter' => $this->t('Manual check: is this embed a keyboard trap?'),
        'embedCustom' => $this->t('Manual check: is this embedded content accessible?'),
        'linkTextIsGeneric' => $this->t('Manual check: is this link meaningful and concise?'),
        'linkTextIsURL' => $this->t('Manual check: is this link text a URL?'),
        'linkDocument' => $this->t('Manual check: is the linked document accessible?'),
        'textUppercase' => $this->t('Manual check: is this uppercase text needed?'),
        'embedVideo' => $this->t('Manual check: is this video accurately captioned?'),
        'embedVisualization' => $this->t('Manual check: is this visualization accessible?'),
        'altPartOfLinkWithText' => $this->t('Manual check: link contains both text and an image'),
        'headingIsLong' => $this->t('Manual check: long heading'),
        'altImageOf' => $this->t('Manual check: possibly redundant text in alt'),
        'textPossibleHeading' => $this->t('Manual check: should this be a heading?'),
        'textPossibleList' => $this->t('Manual check: should this have list formatting?'),
        'headingLevelSkipped' => $this->t('Manual check: was a heading level skipped?'),
        'altLong' => $this->t('Manual check: very long alternative text'),
      ],
      '#default_value' => $config->get('ignore_tests') ?? [],
    ];

    $form['adv']['tests']['download_links'] = [
      '#title' => $this->t("Remind the editor that these linked documents need a manual check"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => "a[href$='.pdf'], a[href*='.pdf?']",
      '#description' => $this->t("Add or remove filetypes. Set to \"false\" to disable the test altogether. Providing any value will override the default, which is <code><em>a[href$='.pdf'], a[href*='.pdf?']</em></code>. <br>To test for more document types, use <code>a[href$='.pdf'], a[href*='.pdf?'], a[href$='.doc'], a[href$='.docx'], a[href*='.doc?'], a[href*='.docx?'], a[href$='.ppt'], a[href$='.pptx'], a[href*='.ppt?'], a[href*='.pptx?'], a[href^='https://docs.google']</code>"),
      '#default_value' => $config->get('download_links'),
    ];

    $form['adv']['tests']['embedded_content_warning'] = [
      '#title' => $this->t("Remind editor that content in these embeds needs manual review"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Provide a comma-separated list of selectors you wish to flag for the editor, e.g.: <code><em>.my-embedded-feed, #my-social-link-block</em></code>.'),
      '#default_value' => $config->get('embedded_content_warning'),
    ];

    $form['adv']['tests']['links'] = [
      '#type' => 'fieldset',
      '#title' => t('Link checks'),
      '#markup' => t('Default settings should work with both <a href="https://www.drupal.org/project/linkpurpose" target="_blank">Link Purpose Icons</a> and <a target="_blank" href="https://www.drupal.org/project/extlink">External Links</a>.'),
    ];
    $form['adv']['tests']['links']['link_ignore_selector'] = [
      '#title' => $this->t("Remove elements that match these selectors before testing link text"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => $config->get('link_ignore_selector'),
      '#description' =>
      $this->t('Provide a CSS selector of elements your modules programmatically add to links (usually external or open-in-new-window links), so they can be ignored when the link text is checked for the "link has no text" and "link text is not meaningful" tests.<br>E.g.: <code><em>.this, .that</em></code>'),
      '#default_value' => $config->get('link_ignore_selector'),
    ];

    $form['adv']['tests']['links']['ignore_link_strings'] = [
      '#title' => $this->t("Remove these strings before testing link text"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => "(link is external)|(link sends email)",
      '#description' => $this->t('Provide a Regex of strings your modules programmatically add to links to hint a purpose (external, mail, phone, open-in-new-window), so they can be ignored when the link text is checked for the "link has no text" and "link text is not meaningful" tests. Escape characters as needed to form a valid regex; e.g.: <br><code><em>(link is external)|(link sends email)</em></code>'),
      '#default_value' => $config->get('ignore_link_strings'),
    ];

    $form['adv']['tests']['links']['link_strings_new_windows'] = [
      '#title' => $this->t("Strings in links that indicate new windows"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => "(download)|(window)|(tab)",
      '#description' => $this->t('Provide a Regex of strings your modules programmatically add to links to indicate open-in-new-window links. These links will not be flagged by the "is opening a new window expected" test.<br>Escape characters as needed to form a valid regex; the default is: <br><code><em>(download)|(window)|(tab)</em></code>'),
      '#default_value' => $config->get('link_strings_new_windows'),
    ];

    $form['adv']['results'] = [
      '#type' => 'details',
      '#title' => t('Displaying results'),
    ];

    $form['adv']['results']['watch_for_changes'] = [
      '#title' => $this->t("Dynamically refresh if new content appears"),
      '#type' => 'select',
      '#options' => [
        'true' => $this->t('Watch for changes anywhere on the page'),
        'checkRoots' => $this->t('Only watch for changes to content containers present on load'),
        'false' => $this->t('Do not watch for changes'),
      ],
      '#default_value' => $config->get('watch_for_changes'),
      '#description' => $this->t('Set to "anywhere" if changes are being missed, set to "ignore" if you notice performance issues. Themes and modules can also call <code>Ed11y.incrementalCheck()</code> to refresh results.'),
    ];

    $form['adv']['results']['no_load'] = [
      '#title' => $this->t("Disable the scanner if these elements are detected"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Provide a comma-separated list of selectors that disable the scanner when present; e.g  (<code><em>body.page-node-type-example, .tabs__link[href="/node/4/edit"]</em></code>.'),
      '#default_value' => $config->get('no_load'),
    ];

    $form['adv']['results']['disable_live'] = [
      '#title' => $this->t("Do not check any content while it is being edited"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('disable_live'),
      '#description' => $this->t('Use the previous field ("Disable the scanner if these elements are detected") to only prevent checking certain specific forms. E.g., setting it to <code><em>form[id^="node-article"]</em></code> would exclude the Article add and edit forms.'),
    ];
    $form['adv']['results']['ignore_all_if_absent'] = [
      '#title' => $this->t("Hide all alerts if none of these elements are present"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Used to limit toggle to nodes where the user can edit something. Suggested selectors: (<code><em>.contextual-region a[href*="/edit"], .contextual-region a[href*="/manage"]</em></code>).'),
      '#default_value' => $config->get('ignore_all_if_absent'),
    ];

    $form['adv']['results']['panel_no_cover'] = [
      '#title' => $this->t("Don't cover these other widgets"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Provide a comma-separated list of selectors for other things that appear in the bottom right of the page. <br>If nothing is set, Editoria11y will automatically slide left to accommodate <code><em>#klaro_toggle_dialog, #klaro-cookie-notice .same-page-preview-dialog.ui-dialog-position-side</em></code>.'),
      '#default_value' => $config->get('panel_no_cover'),
    ];

    $form['adv']['results']['hidden_handlers'] = [
      '#title' => $this->t("Theme JS will handle revealing hidden tooltips inside these containers"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Editoria11y detects hidden tooltips and warns the user when they try to jump to them from the panel. For elements on this list, Editoria11y will <a href="https://itmaybejj.github.io/editoria11y/#dealing-with-alerts-on-hidden-or-size-constrained-content">dispatch a JS event</a> instead of a warning, so custom JS in your theme can first reveal the hidden tip (e.g., open an accordion or tab panel).'),
      '#default_value' => $config->get('hidden_handlers'),
    ];

    $form['adv']['results']['element_hides_overflow'] = [
      '#title' => $this->t("Elements with overflow hidden"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Sometimes buttons get drawn and visually truncated outside the bounds of a positioned element. Provide a selector list.'),
      '#default_value' => $config->get('element_hides_overflow'),
    ];

    $form['adv']['editing'] = [
      '#type' => 'details',
      '#title' => $this->t('Content positioning: edit links & in-editor heading levels'),
    ];

    $form['adv']['editing']['hide_edit_links'] = [
      '#title' => $this->t("Don't show edit links on tips in these containers"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Tips show copies of the "Edit" and "Layout" links for nodes, users and taxonomy terms. These links are not helpful on lists of content from remote nodes.<br>Provide a comma-separated list of page sections of where these links should not show, E.g.: <code><em>#sidebar-menu, .news-feed</em></code>.<br>To hide the links <strong>everywhere</strong>, set this field to an asterisk (<code><em>*</em></code>).<br>To modify the links, <a href="https://editoria11y.princeton.edu/configuration/#modify-tips" target="_blank">use the ed11yPop event</a> in your theme JS.'),
      '#default_value' => $config->get('hide_edit_links'),
    ];
    $form['adv']['editing']['headings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Heading outline position of editable content'),
      '#markup' => '<p>' . $this->t('To check headings in CKEditor, Editoria11y needs to know what the first heading level should be in this field. Body fields should generally be at the h2 level.') . '</p>',
    ];
    $form['adv']['editing']['headings']['live_h2'] = [
      '#title' => $this->t("H2 level fields (body content)"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Body fields on nodes are preceded by an h1, and their heading outline should start with an h2. Ideally set this for top-level body fields for each of your content types, and set blocks and embedded nodes to h3 or h4.
        <br>Set all content types: <code><em>form[id^="node-"] #edit-body-wrapper .ck-content</em></code>
        <br>Set specific content types: <code><em>form[id^="node-"] #edit-body-wrapper .ck-content</em></code>
        <br>Set up for Gutenberg: <code><em>form[id^="node-"] #edit-body-wrapper .is-root-container</em></code>'),
      '#default_value' => $config->get('live_h2'),
    ];
    $form['adv']['editing']['headings']['live_h3'] = [
      '#title' => $this->t("H3 level fields (blocks or paragraphs with separate titles)"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Sometimes inline and layout builder blocks are grouped under an h2 from a separate field, so their highest heading level should be h3.'),
      '#default_value' => $config->get('live_h3'),
    ];
    $form['adv']['editing']['headings']['live_h4'] = [
      '#title' => $this->t("H4 level fields (deeply nested blocks or paragraphs)"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Sometimes inline and layout builder blocks are grouped under an h3 from a separate field, so their highest heading level should be h4.'),
      '#default_value' => $config->get('live_h4'),
    ];
    $form['adv']['editing']['headings']['live_h_inherit'] = [
      '#title' => $this->t("Inline editors that appear in context in the frontend theme"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Inline editors allow for editing blocks in place, in the frontend theme, surrounded by the actual rendered content, and should inherit their heading level from the surrounding content. The most likely needed selector is <code>.ck-editor__editable_inline</code>'),
      '#default_value' => $config->get('live_h_inherit'),
    ];

    $form['adv']['theme'] = [
      '#type' => 'details',
      '#title' => t('Web components & custom tests'),
    ];

    $form['adv']['theme']['shadow_components'] = [
      '#title' => $this->t("Check inside these specific Web components"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => "",
      '#description' => $this->t("Provide selectors for elements with <a href='https://developer.mozilla.org/en-US/docs/Web/Web_Components'>shadow DOM</a> you want tested. E.g.: <code><em>my-fancy-accordion-widget, my-magical-slideshow</em></code>."),
      '#default_value' => $config->get('shadow_components'),
    ];
    $form['adv']['theme']['detect_shadow'] = [
      '#title' => $this->t("Auto-detect any Web components"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('detect_shadow'),
      '#description' => $this->t('This is easier to configure than specifying components, but may slow test runs on very complicated pages.'),
    ];
    $form['adv']['theme']['custom_tests'] = [
      '#title' => $this->t('Custom tests'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 999,
      '#description' => $this->t('Set to the number of other themes or modules that will be <a href="https://editoria11y.princeton.edu/configuration/#customtests">injecting custom result JS events</a>.'),
      '#default_value' => (int) $config->get('custom_tests'),
    ];
    $form['adv']['sync'] = [
      '#type' => 'details',
      '#title' => t('Syncing results to reports'),
      '#markup' => '<p>' . $this->t("Remember that results only sync to the dashboard when viewing nodes. Results shown while editing or viewing previews or revisions will not sync.") . '</p>',
    ];
    $form['adv']['sync']['redundant_prefix'] = [
      '#title' => $this->t("Remove redundant base url from URLs"),
      '#type' => 'textfield',
      '#default_value' => $config->get('redundant_prefix'),
      '#description' => $this->t('Provide base URL ("/mysite") if your site is installed in a subdirectory. Subdirectories tend to get duplicated (/mysite/mysite/mypage) and throw errors from the API.'),
    ];
    $form['adv']['sync']['preserve_params'] = [
      '#title' => $this->t("Preserve query parameters"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'search,page,keys',
      '#default_value' => $config->get('preserve_params'),
      '#description' => $this->t('The dashboard ignores most parameters: results for both /news?f=1 and /news?f=2 will show up as just /news. Provide a comma separated list of parameters that are meaningful, and should appear as separate pages in results.'),
    ];

    $form['adv']['sync']['disable_sync'] = [
      '#title' => $this->t("Disable sync altogether"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('disable_sync'),
      '#description' => $this->t('Syncing test results back to Drupal is required for the <a target="_blank" href="/admin/reports/editoria11y">issue</a> and <a target="_blank" href="/admin/reports/editoria11y/dismissals">dismissal</a> dashboards and "mark OK" buttons.'),
    ];

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('editoria11y.settings')
      ->set('ignore_elements', $form_state->getValue('ignore_elements'))
      ->set('panel_no_cover', $form_state->getValue('panel_no_cover'))
      ->set('hide_edit_links', $form_state->getValue('hide_edit_links'))
      ->set('assertiveness', $form_state->getValue('assertiveness'))
      ->set('no_load', $form_state->getValue('no_load'))
      ->set('disable_sync', $form_state->getValue('disable_sync'))
      ->set('ed11y_theme', $form_state->getValue('ed11y_theme'))
      ->set('ignore_all_if_absent', $form_state->getValue('ignore_all_if_absent'))
      ->set('content_root', $form_state->getValue('content_root'))
      ->set('shadow_components', $form_state->getValue('shadow_components'))
      ->set('ignore_tests', $form_state->getValue('ignore_tests'))
      ->set('detect_shadow', $form_state->getValue('detect_shadow'))
      ->set('watch_for_changes', $form_state->getValue('watch_for_changes'))
      ->set('download_links', $form_state->getValue('download_links'))
      ->set('embedded_content_warning', $form_state->getValue('embedded_content_warning'))
      ->set('hidden_handlers', $form_state->getValue('hidden_handlers'))
      ->set('element_hides_overflow', $form_state->getValue('element_hides_overflow'))
      ->set('live_h_inherit', $form_state->getValue('live_h_inherit'))
      ->set('live_h2', $form_state->getValue('live_h2'))
      ->set('live_h3', $form_state->getValue('live_h3'))
      ->set('live_h4', $form_state->getValue('live_h4'))
      ->set('disable_live', $form_state->getValue('disable_live'))
      ->set('link_strings_new_windows', $form_state->getValue('link_strings_new_windows'))
      ->set('ignore_link_strings', $form_state->getValue('ignore_link_strings'))
      ->set('link_ignore_selector', $form_state->getValue('link_ignore_selector'))
      ->set('preserve_params', $form_state->getValue('preserve_params'))
      ->set('redundant_prefix', $form_state->getValue('redundant_prefix'))
      ->set('custom_tests', $form_state->getValue('custom_tests'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
