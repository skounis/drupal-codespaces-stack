<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca\PluginManager\Condition;

/**
 * Kernel tests for the "eca_state" condition plugin.
 *
 * @group eca
 * @group eca_state
 */
class CompareStateValueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
  ];

  /**
   * ECA condition plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Condition|null
   */
  protected ?Condition $conditionManager;

  /**
   * ECA state service.
   *
   * @var \Drupal\eca\EcaState
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    $this->conditionManager = \Drupal::service('plugin.manager.eca.condition');
    $this->state = \Drupal::service('eca.state');
  }

  /**
   * Tests form field comparison.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testFormField(): void {
    $key = 'eca_state_key';
    $value = $this->randomString(32);
    $this->state->set($key, $value);
    $config = [
      'key' => $key,
      'value' => $value,
      'operator' => StringComparisonBase::COMPARE_EQUALS,
      'type' => StringComparisonBase::COMPARE_TYPE_VALUE,
      'case' => FALSE,
      'negate' => FALSE,
    ];
    /** @var \Drupal\eca_form\Plugin\ECA\Condition\FormFieldValue $condition */
    $condition = $this->conditionManager->createInstance('eca_state', $config);
    $this->assertTrue($condition->evaluate(), 'State value equals expected value.');
  }

}
