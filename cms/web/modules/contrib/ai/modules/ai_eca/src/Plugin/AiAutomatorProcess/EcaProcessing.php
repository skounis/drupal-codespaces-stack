<?php

namespace Drupal\ai_eca\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The eca processor.
 */
#[AiAutomatorProcessRule(
  id: 'eca',
  title: new TranslatableMarkup('ECA'),
  description: new TranslatableMarkup('Uses ECA to trigger the rule. You need to set it up in ECA after setting it up here.'),
)]
class EcaProcessing implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * The batch.
   */
  protected array $batch;

  /**
   * AI Runner.
   */
  protected AiAutomatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructor.
   */
  final public function __construct(AiAutomatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger, ModuleHandlerInterface $moduleHandler) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_automator.rule_runner'),
      $container->get('logger.factory'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $interpolatorConfig) {

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {

  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {

  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Check so ECA Content is installed.
    return $this->moduleHandler->moduleExists('eca_content');
  }

}
