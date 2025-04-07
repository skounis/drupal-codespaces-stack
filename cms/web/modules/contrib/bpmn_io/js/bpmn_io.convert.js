(function ($, Drupal) {

  Drupal.bpmn_io_convert = {
    initialized: false
  };

  Drupal.behaviors.bpmn_io_convert = {};

  /**
   * Import the elements.
   *
   * @param root
   *   The root element.
   */
  Drupal.behaviors.bpmn_io_convert.importElements = async function (root) {
    const bpmnFactory = Drupal.bpmn_io.modeller.get('bpmnFactory'),
      elementRegistry = Drupal.bpmn_io.modeller.get('elementRegistry'),
      elementFactory = Drupal.bpmn_io.modeller.get('elementFactory'),
      modeling = Drupal.bpmn_io.modeller.get('modeling');
    // Set metadata.
    const updateProperties = {
      name: drupalSettings.bpmn_io_convert.metadata.name
    };
    if (drupalSettings.bpmn_io_convert.metadata.version) {
      updateProperties['camunda:versionTag'] = drupalSettings.bpmn_io_convert.metadata.version;
    }
    modeling.updateProperties(root, updateProperties);

    // Create elements.
    Object.keys(drupalSettings.bpmn_io_convert.elements).forEach((id) => {
      const bpmnType = `bpmn:${drupalSettings.bpmn_io_convert.bpmn_mapping[id]}`;
      // Skip conditions as they are not elements.
      if (bpmnType === 'bpmn:SequenceFlow') {
        return;
      }

      const data = drupalSettings.bpmn_io_convert.elements[id];

      // Create the 'collection' for the configuration of the plugin.
      const extEl = Drupal.behaviors.bpmn_io_convert.createExtensionElements(data);

      // Create the object that contains our "business" logic.
      const objectData = {id};
      if (data.label) {
        objectData.name = data.label;
      }
      if (data.plugin) {
        objectData.modelerTemplate = `org.drupal.${drupalSettings.bpmn_io_convert.template_mapping[id]}.${data.plugin}`
      }
      if (extEl) {
        objectData.extensionElements = extEl;
      }
      const businessObject = bpmnFactory.create(bpmnType, objectData);

      // Create the element shape.
      const el = elementFactory.createShape({type: bpmnType, businessObject});

      // Add the shape to the diagram and attach it to the process.
      modeling.createShape(el, {x: Math.floor(Math.random() * 400) + 1, y: Math.floor(Math.random() * 400) + 1}, root);
    });

    // Connect elements.
    Object.keys(drupalSettings.bpmn_io_convert.elements).forEach((id) => {
      const data = drupalSettings.bpmn_io_convert.elements[id];
      if (!('successors' in data) || data.successors.length === 0) {
        return;
      }

      data.successors.forEach((next) => {
        // Determine source and target.
        const source = elementRegistry.get(id);
        const target = elementRegistry.get(next.id);
        const attrs = {type: 'bpmn:SequenceFlow'};

        // Apply condition to the connection?
        if (next.condition) {
          const conditionData = drupalSettings.bpmn_io_convert.elements[next.condition]
          const bpmnType = `bpmn:${drupalSettings.bpmn_io_convert.bpmn_mapping[next.condition]}`;

          // Create the 'collection' for the configuration of the condition.
          const extEl = Drupal.behaviors.bpmn_io_convert.createExtensionElements(conditionData);

          // Create the object that contains our "business" logic.
          attrs.businessObject = bpmnFactory.create(bpmnType, {
            id: next.condition,
            modelerTemplate: `org.drupal.${drupalSettings.bpmn_io_convert.template_mapping[next.condition]}.${conditionData.plugin}`,
            extensionElements: extEl
          });
        }

        modeling.createConnection(source, target, attrs, root);
      });
    });

    // Mark the model as 'initialized'.
    Drupal.bpmn_io_convert.initialized = true;

    try {
      await Drupal.bpmn_io.autoLayout();

      // Save and redirect to the actual edit-form.
      await Drupal.bpmn_io.export();
      setTimeout(() => {
        window.location = drupalSettings.bpmn_io_convert.metadata.redirect_url;
      }, 1000);
    } catch (err) {
      console.log(err);
    }
  };

  /**
   * Create an ExtensionElements-object.
   *
   * @param data
   *   The data to convert.
   *
   * @returns {bpmn:ExtensionElements}|null
   *   Returns the object.
   */
  Drupal.behaviors.bpmn_io_convert.createExtensionElements = function (data) {
    if (!data.plugin) {
      return null;
    }

    const moddle = Drupal.bpmn_io.modeller.get('moddle');
    const extEl = moddle.create('bpmn:ExtensionElements');

    const property = moddle.create('camunda:Property');
    property.name = 'pluginid';
    property.value = data.plugin;
    const properties = moddle.create('camunda:Properties');
    properties.get('values').push(property);
    extEl.get('values').push(properties);

    if ('configuration' in data) {
      Object.keys(data.configuration).forEach((key) => {
        const field = moddle.create('camunda:Field');
        field.name = key;
        field.string = data.configuration[key];

        if (typeof field.string === 'boolean') {
          field.string = field.string ? 'yes' : 'no';
        }

        extEl.get('values').push(field);
      });
    }

    return extEl;
  }

})(jQuery, Drupal);
