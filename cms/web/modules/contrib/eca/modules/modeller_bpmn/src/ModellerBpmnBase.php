<?php

namespace Drupal\eca_modeller_bpmn;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormState;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Model;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\EcaPluginBase;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Plugin\ECA\Modeller\ModellerBase;
use Drupal\eca\Plugin\ECA\Modeller\ModellerInterface;
use Drupal\eca\Service\Modellers;
use Drupal\eca_ui\Service\TokenBrowserService;
use Mtownsend\XmlToArray\XmlToArray;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract class for BPMN modellers.
 *
 * Providing generic functionality which is similar to all such modellers.
 */
abstract class ModellerBpmnBase extends ModellerBase {

  /**
   * The model data as an XML string.
   *
   * @var string
   */
  protected string $modeldata;

  /**
   * The unserialized model data as an XML object.
   *
   * @var array
   */
  protected array $xmlModel;

  /**
   * The filename of the BPMN model, if saved in the file system.
   *
   * @var string
   */
  protected string $filename;

  /**
   * The DOM of the XML data for detailed processing.
   *
   * @var \DOMDocument
   */
  protected \DOMDocument $doc;

  /**
   * The DOM Xpath object for DOM queries.
   *
   * @var \DOMXPath
   */
  protected \DOMXPath $xpath;

  /**
   * ECA token browser service.
   *
   * @var \Drupal\eca_ui\Service\TokenBrowserService
   */
  protected TokenBrowserService $tokenBrowserService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenBrowserService = $container->get('eca_ui.service.token_browser');
    return $instance;
  }

  /**
   * Prepares the data for further updates processes.
   *
   * @param string $data
   *   The serialized data of this model.
   */
  protected function prepareForUpdate(string $data): void {
    $this->modeldata = $data;
    $this->xmlModel = XmlToArray::convert($this->modeldata);
    $this->doc = new \DOMDocument();
    $this->doc->loadXML($this->modeldata);
    $this->xpath = new \DOMXPath($this->doc);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareForExport(): void {
    $this->prepareForUpdate($this->eca->getModel()->getModeldata());
  }

  /**
   * Return the XML namespace prefix used by the BPMN modeller.
   *
   * @return string
   *   The namespace prefix used by the current modeller.
   */
  protected function xmlNsPrefix(): string {
    return '';
  }

  /**
   * Prepares data for a new and empty BPMN model.
   *
   * @return string
   *   The model data.
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();
    $emptyBpmn = file_get_contents($this->extensionPathResolver->getPath('module', 'eca_modeller_bpmn') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'empty.bpmn');
    return str_replace([
      'SIDPLACEHOLDER1',
      'SIDPLACEHOLDER2',
      'IDPLACEHOLDER',
    ], [
      'sid-' . $this->uuid->generate(),
      'sid-' . $this->uuid->generate(),
      $id,
    ], $emptyBpmn);
  }

  /**
   * {@inheritdoc}
   */
  public function generateId(): string {
    $random = new Random();
    return 'Process_' . $random->name(7);
  }

  /**
   * {@inheritdoc}
   */
  public function createNewModel(string $id, string $model_data, ?string $filename = NULL, bool $save = FALSE): Eca {
    $eca = Eca::create(['id' => mb_strtolower($id)]);
    $eca->getModel()->setModeldata($model_data);
    $this->setConfigEntity($eca);
    if ($save) {
      $this->save($model_data, $filename);
      $eca = $this->getEca();
    }
    return $eca;
  }

  /**
   * {@inheritdoc}
   */
  public function save(string $data, ?string $filename = NULL, ?bool $status = NULL): bool {
    $this->prepareForUpdate($data);
    $this->filename = $filename ?? '';
    if ($status !== NULL) {
      $this->xmlModel[$this->xmlNsPrefix() . 'process']['@attributes']['isExecutable'] = $status ? 'true' : 'false';
    }
    return $this->modellerServices->saveModel($this);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \DOMException
   */
  public function updateModel(Model $model): bool {
    $this->prepareForUpdate($this->eca->getModel()->getModeldata());
    $changed = FALSE;
    $idxExtension = $this->xmlNsPrefix() . 'extensionElements';
    foreach ($this->getTemplates() as $template) {
      foreach ($template['appliesTo'] as $type) {
        switch ($type) {
          case 'bpmn:Event':
            $objects = $this->getStartEvents();
            break;

          case 'bpmn:SequenceFlow':
            $objects = $this->getSequenceFlows();
            break;

          case 'bpmn:Task':
            $objects = $this->getTasks();
            break;

          default:
            $objects = [];

        }
        foreach ($objects as $object) {
          if (isset($object['@attributes']['modelerTemplate']) && $template['id'] === $object['@attributes']['modelerTemplate']) {
            $fields = $this->findFields($object[$idxExtension]);
            $id = $object['@attributes']['id'];
            /**
             * @var \DOMElement|null $element
             */
            $element = $this->xpath->query("//*[@id='$id']")->item(0);
            if ($element) {
              /**
               * @var \DOMElement|null $extensions
               */
              $extensions = $this->xpath->query("//*[@id='$id']/$idxExtension")
                ->item(0);
              if (!$extensions) {
                $node = $this->doc->createElement($idxExtension);
                $extensions = $element->appendChild($node);
              }
              foreach ($template['properties'] as $property) {
                switch ($property['binding']['type']) {
                  case 'camunda:property':
                    if ($this->findProperty($object[$idxExtension], $property['binding']['name']) !== $property['value']) {
                      $element->setAttribute($property['binding']['name'], $property['value']);
                      $changed = TRUE;
                    }
                    break;

                  case 'camunda:field':
                    if (isset($fields[$property['binding']['name']])) {
                      // Field exists, remove it from the list.
                      unset($fields[$property['binding']['name']]);
                    }
                    else {
                      $fieldNode = $this->doc->createElement('camunda:field');
                      $fieldNode->setAttribute('name', $property['binding']['name']);
                      $valueNode = $this->doc->createElement('camunda:string');
                      $valueNode->textContent = $property['value'];
                      $fieldNode->appendChild($valueNode);
                      $extensions->appendChild($fieldNode);
                      $changed = TRUE;
                    }
                    break;
                }
              }
              // Remove remaining fields from the model.
              foreach ($fields as $name => $value) {
                /**
                 * @var \DOMElement $fieldElement
                 */
                if ($fieldElement = $this->xpath->query("//*[@id='$id']/$idxExtension/camunda:field[@name='$name']")
                  ->item(0)) {
                  $extensions->removeChild($fieldElement);
                  $changed = TRUE;
                }
              }
            }
          }
        }
      }
    }
    if ($changed) {
      $this->prepareForUpdate($this->doc->saveXML());
      $model->setModeldata($this->modeldata);
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function enable(): ModellerInterface {
    $this->prepareForUpdate($this->eca->getModel()->getModeldata());
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('isExecutable', 'true');
    }
    try {
      $this->save($this->doc->saveXML());
    }
    catch (\LogicException | EntityStorageException $e) {
      $this->messenger->addError($e->getMessage());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(): ModellerInterface {
    $this->prepareForUpdate($this->eca->getModel()->getModeldata());
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('isExecutable', 'false');
    }
    try {
      $this->save($this->doc->saveXML());
    }
    catch (\LogicException | EntityStorageException $e) {
      $this->messenger->addError($e->getMessage());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(): ?Eca {
    $this->prepareForUpdate($this->eca->getModel()->getModeldata());
    $id = $this->generateId();
    /** @var \DOMElement|null $element */
    $element = $this->xpath->query("//*[@id='{$this->getId()}']")->item(0);
    if ($element) {
      $element->setAttribute('id', $id);
      $element->setAttribute('name', $this->getLabel() . ' (' . $this->t('clone') . ')');
    }
    try {
      return $this->createNewModel($id, $this->doc->saveXML(), NULL, TRUE);
    }
    catch (\LogicException | EntityStorageException $e) {
      $this->messenger->addError($e->getMessage());
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * {@inheritdoc}
   */
  public function setModeldata(string $data): ModellerInterface {
    $this->prepareForUpdate($data);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getModeldata(): string {
    return $this->modeldata;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process']['@attributes']['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process']['@attributes']['name'] ?? 'noname';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    $tags = isset($this->xmlModel[$process][$extensions]) ?
      explode(',', $this->findProperty($this->xmlModel[$process][$extensions], 'Tags')) :
      [];
    array_walk($tags, static function (&$item) {
      $item = trim((string) $item);
    });
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): array {
    $this->prepareForExport();
    $process = $this->xmlNsPrefix() . 'process';
    $extensions = $this->xmlNsPrefix() . 'extensionElements';
    $changelog = [];
    if (isset($this->xmlModel[$process][$extensions])) {
      $v = 1;
      while ($item = $this->findProperty($this->xmlModel[$process][$extensions], 'Changelog v' . $v)) {
        $changelog['v' . $v] = $item;
        $v++;
      }
    }
    return $changelog;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process'][$this->xmlNsPrefix() . 'documentation'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return mb_strtolower($this->xmlModel[$this->xmlNsPrefix() . 'process']['@attributes']['isExecutable'] ?? 'true') === 'true';
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->xmlModel[$this->xmlNsPrefix() . 'process']['@attributes']['versionTag'] ?? '';
  }

  /**
   * Returns all the startEvent (events) objects from the XML model.
   *
   * @return array
   *   The list of all start events in the model data.
   */
  private function getStartEvents(): array {
    $events = $this->xmlModel[$this->xmlNsPrefix() . 'process'][$this->xmlNsPrefix() . 'startEvent'] ?? [];
    if (isset($events['@attributes'])) {
      return [$events];
    }
    return $events;
  }

  /**
   * Returns all the task objects (actions) from the XML model.
   *
   * @return array
   *   The list of all tasks in the model data.
   */
  private function getTasks(): array {
    $actions = $this->xmlModel[$this->xmlNsPrefix() . 'process'][$this->xmlNsPrefix() . 'task'] ?? [];
    if (isset($actions['@attributes'])) {
      return [$actions];
    }
    return $actions;
  }

  /**
   * Returns all the sequenceFlow objects (condition) from the XML model.
   *
   * @return array
   *   The list of all sequence flows in the model data.
   */
  private function getSequenceFlows(): array {
    $conditions = $this->xmlModel[$this->xmlNsPrefix() . 'process'][$this->xmlNsPrefix() . 'sequenceFlow'] ?? [];
    if (isset($conditions['@attributes'])) {
      return [$conditions];
    }
    return $conditions;
  }

  /**
   * Returns all the gateway objects from the XML model.
   *
   * @return array
   *   The list of all gateways in the model data.
   */
  private function getGateways(): array {
    $types = [
      $this->conditionServices::GATEWAY_TYPE_EXCLUSIVE => 'exclusiveGateway',
      $this->conditionServices::GATEWAY_TYPE_PARALLEL => 'parallelGateway',
      $this->conditionServices::GATEWAY_TYPE_INCLUSIVE => 'inclusiveGateway',
      $this->conditionServices::GATEWAY_TYPE_COMPLEX => 'complexGateway',
      $this->conditionServices::GATEWAY_TYPE_EVENTBASED => 'eventBasedGateway',
    ];
    $gateways = [];
    foreach ($types as $key => $type) {
      $objects = $this->xmlModel[$this->xmlNsPrefix() . 'process'][$this->xmlNsPrefix() . $type] ?? [];
      if (isset($objects['@attributes'])) {
        $objects = [$objects];
      }
      foreach ($objects as $object) {
        $object['type'] = $key;
        $gateways[] = $object;
      }
    }
    return $gateways;
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(Eca $eca): ModellerInterface {
    $this->eca = $eca;
    $this->eca->resetComponents();
    $idxExtension = $this->xmlNsPrefix() . 'extensionElements';

    $this->hasError = FALSE;
    $flow = [];
    foreach ($this->getSequenceFlows() as $sequenceFlow) {
      if (isset($sequenceFlow[$idxExtension])) {
        $pluginId = $this->findProperty($sequenceFlow[$idxExtension], 'pluginid');
        $condition = $this->findAttribute($sequenceFlow, 'id');
        if (!empty($pluginId) && !empty($condition)) {
          if (!$eca->addCondition(
            $condition,
            $pluginId,
            $this->findFields($sequenceFlow[$idxExtension])
          )) {
            $this->hasError = TRUE;
          }
        }
        else {
          $condition = '';
        }
      }
      else {
        $condition = '';
      }
      $flow[$this->findAttribute($sequenceFlow, 'sourceRef')][] = [
        'id' => $this->findAttribute($sequenceFlow, 'targetRef'),
        'condition' => $condition,
      ];
    }

    foreach ($this->getGateways() as $gateway) {
      $gatewayId = $this->findAttribute($gateway, 'id');
      $eca->addGateway($gatewayId, $gateway['type'], $flow[$gatewayId] ?? []);
    }

    foreach ($this->getStartEvents() as $startEvent) {
      $extension = $startEvent[$idxExtension] ?? [];
      $pluginId = $this->findProperty($extension, 'pluginid');
      if (empty($pluginId)) {
        continue;
      }
      if (!$eca->addEvent(
        $this->findAttribute($startEvent, 'id'),
        $pluginId,
        $this->findAttribute($startEvent, 'name'),
        $this->findFields($extension),
        $flow[$this->findAttribute($startEvent, 'id')] ?? []
      )) {
        $this->hasError = TRUE;
      }
    }

    foreach ($this->getTasks() as $task) {
      $extension = $task[$idxExtension] ?? [];
      $pluginId = $this->findProperty($extension, 'pluginid');
      if (empty($pluginId)) {
        continue;
      }
      if (!$eca->addAction(
        $this->findAttribute($task, 'id'),
        $pluginId,
        $this->findAttribute($task, 'name'),
        $this->findFields($extension),
        $flow[$this->findAttribute($task, 'id')] ?? []
      )) {
        $this->hasError = TRUE;
      }
    }

    return $this;
  }

  /**
   * Prepares the plugin's configuration form and catches errors.
   *
   * @param \Drupal\eca\Plugin\ECA\Event\EventInterface|\Drupal\eca\Plugin\ECA\Condition\ConditionInterface|\Drupal\Core\Action\ActionInterface $plugin
   *   The plugin.
   *
   * @return array
   *   The configuration form.
   */
  protected function buildConfigurationForm(EventInterface|ConditionInterface|ActionInterface $plugin): array {
    $form_state = new FormState();
    try {
      if ($plugin instanceof ActionInterface) {
        $form = $this->actionServices->getConfigurationForm($plugin, $form_state) ?? [
          'error_message' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Error in configuration form!!!'),
            '#description' => $this->t('Details can be found in the Drupal error log.'),
          ],
        ];
      }
      else {
        $form = $plugin->buildConfigurationForm([], $form_state);
      }
    }
    catch (\Throwable $ex) {
      // @todo Replace this with some markup when that's supported by bpmn_io.
      $form['error_message'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Error in configuration form!!!'),
        '#description' => $ex->getMessage(),
      ];
    }
    return $form;
  }

  /**
   * Returns all the templates for the modeller UI.
   *
   * This includes templates for events, conditions and actions.
   *
   * @return array
   *   The list of all templates.
   */
  protected function getTemplates(): array {
    $templates = [];
    foreach ($this->modellerServices->events() as $event) {
      $templates[] = $this->properties($event, 'event', 'bpmn:Event', $this->buildConfigurationForm($event));
    }
    foreach ($this->conditionServices->conditions() as $condition) {
      $templates[] = $this->properties($condition, 'condition', 'bpmn:SequenceFlow', $this->buildConfigurationForm($condition));
    }
    foreach ($this->actionServices->actions() as $action) {
      $templates[] = $this->properties($action, 'action', 'bpmn:Task', $this->buildConfigurationForm($action));
    }
    return $templates;
  }

  /**
   * {@inheritdoc}
   */
  public function exportTemplates(): ModellerInterface {
    // Nothing to do by default.
    return $this;
  }

  /**
   * Helper function to build a template for an event, condition or action.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The event, condition or action plugin for which the template should
   *   be build.
   * @param string $plugin_type
   *   The string identifying the plugin type, which is one of event, condition
   *   or action.
   * @param string $applies_to
   *   The string to tell the modeller, to which object type the template will
   *   apply. Valid values are "bpmn:Event", "bpmn:sequenceFlow" or "bpmn:task".
   * @param array $form
   *   An array containing the configuration form of the plugin.
   *
   * @return array
   *   The completed template for BPMN modellers for the given plugin and its
   *   fields.
   */
  protected function properties(PluginInspectionInterface $plugin, string $plugin_type, string $applies_to, array $form): array {
    $properties = [
      [
        'label' => 'Plugin ID',
        'type' => 'Hidden',
        'value' => $plugin->getPluginId(),
        'binding' => [
          'type' => 'camunda:property',
          'name' => 'pluginid',
        ],
      ],
    ];
    $extraDescriptions = [];
    foreach ($this->prepareConfigFields($form, $extraDescriptions) as $field) {
      if (!isset($field['value'])) {
        $value = '';
      }
      elseif (is_scalar($field['value'])) {
        $value = (string) $field['value'];
      }
      elseif (is_object($field['value']) && method_exists($field['value'], '__toString')) {
        $value = $field['value']->__toString();
      }
      else {
        $this->logger->error('Found config field %field in %plugin with non-supported value.', [
          '%field' => $field['label'],
          '%plugin' => $plugin->getPluginId(),
        ]);
        $value = '';
      }
      $property = [
        'label' => $field['label'],
        'type' => $field['type'],
        'value' => $value,
        'editable' => $field['editable'] ?? TRUE,
        'binding' => [
          'type' => 'camunda:field',
          'name' => $field['name'],
        ],
      ];
      if (!empty($field['required'])) {
        $property['constraints']['notEmpty'] = TRUE;
      }
      if (isset($field['description'])) {
        $property['description'] = (string) $field['description'];
      }
      if (isset($field['extras'])) {
        /* @noinspection SlowArrayOperationsInLoopInspection */
        $property = array_merge_recursive($property, $field['extras']);
      }
      $properties[] = $property;
    }
    $extraDescriptions = array_unique($extraDescriptions);
    $pluginDefinition = $plugin->getPluginDefinition();
    $template = [
      'name' => (string) $pluginDefinition['label'],
      'id' => 'org.drupal.' . $plugin_type . '.' . $plugin->getPluginId(),
      'category' => [
        'id' => $pluginDefinition['provider'],
        'name' => EcaPluginBase::$modules[$pluginDefinition['provider']],
      ],
      'appliesTo' => [$applies_to],
      'properties' => $properties,
    ];
    if (isset($pluginDefinition['description']) || $extraDescriptions) {
      $template['description'] = (string) ($pluginDefinition['description'] ?? '') . ' ' . implode(' ', $extraDescriptions);
    }
    if ($doc_url = $this->pluginDocUrl($plugin, $plugin_type)) {
      $template['documentationRef'] = $doc_url;
    }
    return $template;
  }

  /**
   * Builds the URL to the offsite documentation for the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin for which the documentation URL should be build.
   * @param string $plugin_type
   *   The string identifying the plugin type, which is one of event, condition
   *   or action.
   *
   * @return string|null
   *   The URL to the offsite documentation, or NULL if no URL was generated.
   */
  protected function pluginDocUrl(PluginInspectionInterface $plugin, string $plugin_type): ?string {
    if (!($domain = $this->documentationDomain)) {
      return NULL;
    }
    $provider = $plugin->getPluginDefinition()['provider'];
    $basePath = (mb_strpos($provider, 'eca_') === 0) ?
      str_replace('_', '/', $provider) :
      $provider;
    return sprintf('%s/plugins/%s/%ss/%s/', $domain, $basePath, $plugin_type, str_replace([':'], '_', $plugin->getPluginId()));
  }

  /**
   * Return a property of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the property should be returned.
   * @param string $property_name
   *   The name of the property in the BPMN element.
   *
   * @return string
   *   The property's value, default to an empty string.
   */
  protected function findProperty(array $element, string $property_name): string {
    if (isset($element['camunda:properties']['camunda:property'])) {
      $elements = isset($element['camunda:properties']['camunda:property']['@attributes']) ?
        [$element['camunda:properties']['camunda:property']] :
        $element['camunda:properties']['camunda:property'];
      foreach ($elements as $child) {
        if ($child['@attributes']['name'] === $property_name) {
          return $child['@attributes']['value'];
        }
      }
    }
    return '';
  }

  /**
   * Return an attribute of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the attribute should be returned.
   * @param string $attribute_name
   *   The name of the attribute in the BPMN element.
   *
   * @return string
   *   The attribute's value, default to an empty string.
   */
  protected function findAttribute(array $element, string $attribute_name): string {
    return $element['@attributes'][$attribute_name] ?? '';
  }

  /**
   * Return all the field values of a given BPMN element.
   *
   * @param array $element
   *   The BPMN element from which the field values should be returned.
   *
   * @return array
   *   An array containing all the field values, keyed by the field name.
   */
  protected function findFields(array $element): array {
    $fields = [];
    if (isset($element['camunda:field'])) {
      $elements = isset($element['camunda:field']['@attributes']) ? [$element['camunda:field']] : $element['camunda:field'];
      foreach ($elements as $child) {
        $fields[$child['@attributes']['name']] = isset($child['camunda:string']) && is_string($child['camunda:string']) ? $child['camunda:string'] : '';
      }
    }
    return $fields;
  }

  /**
   * Helper function preparing config fields for events, conditions and actions.
   *
   * @param array $form
   *   The array to which the fields should be added.
   * @param array $extraDescriptions
   *   An array receiving all markup "fields" which can be displayed separately
   *   in the UI.
   *
   * @return array
   *   The prepared config fields.
   */
  protected function prepareConfigFields(array $form, array &$extraDescriptions): array {
    // @todo Add support for nested form fields like e.g. in container/fieldset.
    $fields = [];
    foreach ($form as $key => $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $label = $definition['#title'] ?? Modellers::convertKeyToLabel($key);
      $description = $definition['#description'] ?? NULL;
      $value = $definition['#default_value'] ?? '';
      $weight = $definition['#weight'] ?? 0;
      $type = 'String';
      $required = $definition['#required'] ?? FALSE;
      // @todo Map to more proper property types of bpmn-js.
      switch ($definition['#type'] ?? 'markup') {

        case 'hidden':
        case 'actions':
          // The modellers can't handle these types, so we ignore them for
          // the templates.
          continue 2;

        case 'item':
        case 'markup':
        case 'container':
          if (isset($definition['#markup'])) {
            $extraDescriptions[] = (string) $definition['#markup'];
          }
          continue 2;

        case 'textarea':
          $type = 'Text';
          break;

        case 'checkbox':
          $fields[] = $this->checkbox($key, $label, $weight, $description, $value);
          continue 2;

        case 'checkboxes':
        case 'radios':
        case 'select':
          if (!is_array($value)) {
            $options = $this->normalizeOptions(form_select_options($definition));
            $fields[] = $this->optionsField($key, $label, $weight, $description, $options, (string) $value, $required);
            continue 2;
          }
          break;

      }
      if (is_bool($value)) {
        $fields[] = $this->checkbox($key, $label, $weight, $description, $value);
        continue;
      }
      if (is_array($value)) {
        $value = implode(',', $value);
      }
      $field = [
        'name' => $key,
        'label' => $label,
        'weight' => $weight,
        'type' => $type,
        'value' => $value,
        'required' => $required,
      ];
      if ($description !== NULL) {
        $field['description'] = $description;
      }
      $fields[] = $field;
    }

    // Sort fields by weight.
    usort($fields, static function ($f1, $f2) {
      $l1 = (int) $f1['weight'];
      $l2 = (int) $f2['weight'];
      if ($l1 < $l2) {
        return -1;
      }
      if ($l1 > $l2) {
        return 1;
      }
      return 0;
    });

    return $fields;
  }

  /**
   * Normalizes an option list into a flat list of keys and labels.
   *
   * This can be called recursively, e.g. for nested option groups.
   *
   * @param array $formApiOptions
   *   The list of options.
   * @param string $prefix
   *   An optional prefix which will be prepended to the label.
   *
   * @return array
   *   The flat option list.
   */
  protected function normalizeOptions(array $formApiOptions, string $prefix = ''): array {
    $options = [];
    foreach ($formApiOptions as $formApiOption) {
      switch ($formApiOption['type']) {
        case 'option':
          $options[$formApiOption['value']] = $prefix . $formApiOption['label'];
          break;

        case 'optgroup':
          $options += $this->normalizeOptions($formApiOption['options'], $formApiOption['label'] . ': ');
          break;

      }
    }
    return $options;
  }

  /**
   * Prepares a field with options as a drop-down.
   *
   * @param string $name
   *   The field name.
   * @param string $label
   *   The field label.
   * @param int $weight
   *   The field weight for sorting.
   * @param string|null $description
   *   The optional field description.
   * @param array $options
   *   Key/value list of available options.
   * @param string $value
   *   The default value for the field.
   * @param bool $required
   *   The setting, if this field is required to be filled by the user.
   *
   * @return array
   *   Prepared option field.
   */
  protected function optionsField(string $name, string $label, int $weight, ?string $description, array $options, string $value, bool $required = FALSE): array {
    $choices = [];
    foreach ($options as $optionValue => $optionName) {
      $choices[] = [
        'name' => (string) $optionName,
        'value' => (string) $optionValue,
      ];
      if ($required && $value === '') {
        $value = (string) $optionValue;
      }
    }
    $field = [
      'name' => $name,
      'label' => $label,
      'weight' => $weight,
      'type' => 'Dropdown',
      'value' => $value,
      'required' => $required,
      'extras' => [
        'choices' => $choices,
      ],
    ];
    if ($description !== NULL) {
      $field['description'] = $description;
    }
    return $field;
  }

  /**
   * Prepares a field as a checkbox.
   *
   * @param string $name
   *   The field name.
   * @param string $label
   *   The field label.
   * @param int $weight
   *   The field weight for sorting.
   * @param string|null $description
   *   The optional field description.
   * @param bool $value
   *   The default value for the field.
   *
   * @return array
   *   Prepared checkbox field.
   */
  protected function checkbox(string $name, string $label, int $weight, ?string $description, bool $value): array {
    $field = [
      'name' => $name,
      'label' => $label,
      'weight' => $weight,
      'type' => 'Dropdown',
      'value' => $value ? 'yes' : 'no',
      'extras' => [
        'choices' => [
          [
            'name' => 'no',
            'value' => 'no',
          ],
          [
            'name' => 'yes',
            'value' => 'yes',
          ],
        ],
      ],
    ];
    if ($description !== NULL) {
      $field['description'] = $description;
    }
    return $field;
  }

}
