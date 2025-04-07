<?php

namespace Drupal\Tests\easy_email\Functional;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageException;
use Behat\Mink\Element\NodeElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\easy_email\Entity\EasyEmailTypeInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Class EasyEmailTestBase
 */
abstract class EasyEmailTestBase extends BrowserTestBase {

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'text',
    'file',
    'options',
    'token',
    'mailsystem',
    'symfony_mailer_lite',
    'easy_email',
  ];

  /**
   * @var \Drupal\filter\FilterFormatInterface
   */
  protected $htmlFormat;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void{
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/modules');
    $this->createHtmlTextFormat();
    $this->drupalLogout();
    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);
    $this->initBrowserOutputFile();
  }

  protected function createHtmlTextFormat() {
    $edit = [
      'format' => 'html',
      'name' => 'HTML',
    ];
    $this->drupalGet('admin/config/content/formats/add');
    $this->submitForm($edit, t('Save configuration'));
    filter_formats_reset();
    $this->htmlFormat = FilterFormat::load($edit['format']);
  }

  protected function getAdministratorPermissions() {
    $permissions = [
      'administer email types',
      'administer easy_email fields',
      'add email entities',
      'edit email entities',
      'view all email entities',
      'administer filters',
      'administer modules',
    ];
    if (!empty($this->htmlFormat)) {
      $permissions[] = $this->htmlFormat->getPermissionName();
    }
    return $permissions;
  }

  /**
   * @param array $values
   * @param bool $save
   *
   * @return \Drupal\easy_email\Entity\EasyEmailTypeInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTemplate($values = [], $save = TRUE) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $email_template_storage */
    $email_template_storage = \Drupal::entityTypeManager()->getStorage('easy_email_type');
    $template = $email_template_storage->create($values);
    if ($save) {
      $template->save();
    }
    return $template;
  }

  /**
   * Get sent emails captured by the Test Mail Collector.
   *
   * @param array $params
   *   Parameters to use for matching emails.
   *
   * @return array
   */
  protected function getSentEmails(array $params) {
    \Drupal::state()->resetCache();
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $matched_emails = [];
    if (!empty($captured_emails)) {
      foreach ($captured_emails as $email) {
        $is_match = [];
        foreach ($params as $key => $value) {
          $param_match = FALSE;
          if (isset($email[$key]) && is_string($email[$key]) && $email[$key] == $value) {
            $param_match = TRUE;
          }
          elseif (isset($email['params'][$key]) && is_string($email['params'][$key]) && $email['params'][$key] == $value) {
            $param_match = TRUE;
          }
          elseif (isset($email[$key]) && $email[$key] instanceof TranslatableMarkup && $email[$key]->getUntranslatedString() == $value) {
            $param_match = TRUE;
          }
          elseif (isset($email['params'][$key]) && $email['params'][$key] instanceof TranslatableMarkup && $email['params'][$key]->getUntranslatedString() == $value) {
            $param_match = TRUE;
          }
          $is_match[] = $param_match;
        }
        if (count($is_match) == count(array_filter($is_match))) {
          $matched_emails[] = $email;
        }
      }
    }
    return $matched_emails;
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type
   * @param string $field_name
   * @param string $label
   */
  protected function addUserField(EasyEmailTypeInterface $easy_email_type, $field_name = 'field_user', $label = 'User') {
    $field_definition = BaseFieldDefinition::create('entity_reference')
      ->setTargetEntityTypeId('easy_email')
      ->setTargetBundle($easy_email_type->id())
      ->setName($field_name)
      ->setLabel($label)
      ->setRevisionable(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    try {
      $this->createField($field_definition, FALSE);
    }
    catch (\RuntimeException $e) {
      // In this case, field already exists from installation
    }
    catch (EntityStorageException $e) {
      // In this case, field already exists from installation
    }
  }

  /**
   * Creates a configurable field from the given field definition.
   *
   * Borrowed from Drupal Commerce: ConfigurableFieldManager class
   *
   * @param \Drupal\Core\Field\BaseFieldDefinition $field_definition
   *   The field definition.
   * @param bool $lock
   *   Whether the created field should be locked.
   *
   * @throws \InvalidArgumentException
   *   Thrown when given an incomplete field definition (missing name,
   *   target entity type ID, or target bundle).
   * @throws \RuntimeException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createField(BaseFieldDefinition $field_definition, $lock = TRUE) {
    $field_name = $field_definition->getName();
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    if (empty($field_name) || empty($entity_type_id) || empty($bundle)) {
      throw new \InvalidArgumentException('The passed $field_definition is incomplete.');
    }
    // loadByName() is an API that doesn't exist on the storage classes for
    // the two entity types, so we're using the entity classes directly.
    $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
    if (!empty($field)) {
      throw new \RuntimeException(sprintf('The field "%s" already exists on bundle "%s" of entity type "%s".', $field_name, $bundle, $entity_type_id));
    }

    // The field storage might already exist if the field was created earlier
    // on a different bundle of the same entity type.
    if (empty($field_storage)) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'type' => $field_definition->getType(),
        'cardinality' => $field_definition->getCardinality(),
        'settings' => $field_definition->getSettings(),
        'locked' => $lock,
      ]);
      $field_storage->save();
    }

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $field_definition->getLabel(),
      'required' => $field_definition->isRequired(),
      'settings' => $field_definition->getSettings(),
      'default_value' => $field_definition->getDefaultValueLiteral(),
      'default_value_callback' => $field_definition->getDefaultValueCallback(),
    ]);
    $field->save();

    // Show the field on default entity displays, if specified.
    if ($view_display_options = $field_definition->getDisplayOptions('view')) {
      $view_display = $this->getEntityDisplay($entity_type_id, $bundle, 'view');
      $view_display->setComponent($field_name, $view_display_options);
      $view_display->save();
    }
    if ($form_display_options = $field_definition->getDisplayOptions('form')) {
      $form_display = $this->getEntityDisplay($entity_type_id, $bundle, 'form');
      $form_display->setComponent($field_name, $form_display_options);
      $form_display->save();
    }
  }

  /**
   * Gets the entity display for the given entity type and bundle.
   *
   * The entity display will be created if missing.
   *
   * Borrowed from Drupal Commerce: commerce_get_entity_display()
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $display_context
   *   The display context ('view' or 'form').
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The entity display.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityDisplay($entity_type, $bundle, $display_context) {
    if (!in_array($display_context, ['view', 'form'])) {
      throw new \InvalidArgumentException(sprintf('Invalid display_context %s passed to _commerce_product_get_display().', $display_context));
    }

    $storage = \Drupal::entityTypeManager()->getStorage('entity_' . $display_context . '_display');
    $display = $storage->load($entity_type . '.' . $bundle . '.default');
    if (!$display) {
      $display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    return $display;
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type
   * @param string $field_name
   */
  protected function removeField(EasyEmailTypeInterface $easy_email_type, $field_name) {
    $this->drupalGet('admin/structure/email-templates/templates/' . $easy_email_type->id() . '/edit/fields/easy_email.' . $easy_email_type->id() . '.' . $field_name . '/delete');
    $this->submitForm([], 'Delete');
  }

  /**
   * @param \Behat\Mink\Element\NodeElement $iframe
   *
   * @return array
   */
  protected function getIframeUrlAndQuery(NodeElement $iframe) {
    $url = $iframe->getAttribute('src');
    $front_url = Url::fromRoute('<front>', [], ['absolute' => FALSE]);
    $base_url = $front_url->toString();
    $url = preg_replace('#^' . $base_url . '#', '', $url);
    $url = explode('?', $url);
    $query = [];
    if (!empty($url[1])) {
      $query_parts = explode('=', $url[1]);
      for ($i = 0; $i < count($query_parts); $i += 2) {
        $query[$query_parts[$i]] = $query_parts[$i+1];
      }
    }

    return ['path' => $url[0], 'query' => $query];
  }

}
