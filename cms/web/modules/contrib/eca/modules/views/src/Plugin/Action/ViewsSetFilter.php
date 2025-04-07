<?php

namespace Drupal\eca_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Service\YamlParser;
use Drupal\eca_views\Event\ViewsBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Set a views filter value.
 *
 * @Action(
 *   id = "eca_views_set_filter_value",
 *   label = @Translation("Views: Set filter value"),
 *   eca_version_introduced = "2.0.0"
 * )
 */
class ViewsSetFilter extends ConfigurableActionBase {

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->getEvent();
    if (!($event instanceof ViewsBase)) {
      return;
    }

    $id = $this->tokenService->getOrReplace($this->configuration['filter_id']);
    $value = $this->configuration['value'];

    if ($this->configuration['use_yaml']) {
      try {
        $event->getView()->filter[$id]->value = $this->yamlParser->parse($value);
      }
      catch (ParseException) {
        $this->logger->error('Tried parsing a views filter value item in action "eca_views_set_filter_value" as YAML format, but parsing failed.');
        return;
      }
    }
    else {
      $value = $this->tokenService->getOrReplace($value);
      if ($value instanceof DataTransferObject) {
        $value = $value->getValue();
      }
      $event->getView()->filter[$id]->value['value'] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    $event = $this->getEvent();
    if ($event instanceof ViewsBase) {
      $id = $this->tokenService->getOrReplace($this->configuration['filter_id']);
      if (isset($event->getView()->filter[$id])) {
        $result = AccessResult::allowed();
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'filter_id' => '',
      'value' => '',
      'use_yaml' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['filter_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter ID'),
      '#default_value' => $this->configuration['filter_id'],
      '#weight' => -30,
      '#description' => $this->t('The ID of the view filter.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The value of the filter'),
      '#default_value' => $this->configuration['value'],
      '#weight' => -20,
      '#description' => $this->t('The value of the filter. This can either be a string or a YAML array when multiple keys have to be set for that filter.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['use_yaml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Interpret above config value as YAML format'),
      '#default_value' => $this->configuration['use_yaml'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['filter_id'] = $form_state->getValue('filter_id');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['use_yaml'] = !empty($form_state->getValue('use_yaml'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
