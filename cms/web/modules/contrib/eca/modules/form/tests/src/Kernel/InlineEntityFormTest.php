<?php

namespace Drupal\Tests\eca_form\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

/**
 * Kernel tests regarding inline entity forms.
 *
 * @group eca
 * @group eca_form
 */
class InlineEntityFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'filter',
    'text',
    'node',
    'eca',
    'eca_form',
    'entity_reference_revisions',
    'paragraphs',
    'inline_entity_form',
    'eca_test_inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
  }

  /**
   * Tests the result of the provided ECA model that manipulates inline forms.
   *
   * The model is provided by the module eca_test_inline_entity_form.
   */
  public function testInlineEntityFormModel() {
    $page = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'page',
    ]);
    $page->save();

    $paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => $this->randomMachineName(),
    ]);

    $article = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'field_pages' => [$page],
      'field_paragraphs' => [$paragraph],
    ]);
    $article->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'edit');
    $form_object->setEntity($article);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $this->assertTrue($form['field_pages']['widget'][0]['inline_entity_form']['title']['widget'][0]['#disabled'], "The model has set the title field to be disabled.");
    $this->assertTrue($form['field_paragraphs']['widget'][0]['subform']['field_text']['widget'][0]['#disabled'], "The model has set the field_text field to be disabled.");
  }

}
