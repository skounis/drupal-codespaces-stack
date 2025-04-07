function ModelConverter(eventBus) {

  eventBus.on('root.set', function(e) {
    if (
      e.element.isImplicit
      || !Drupal.bpmn_io_convert
      || (Drupal.bpmn_io_convert && Drupal.bpmn_io_convert.initialized)
    ) {
      return;
    }

    Drupal.behaviors.bpmn_io_convert.importElements(e.element);
  });

}

ModelConverter.$inject = ['eventBus'];

export default {
  __init__: ['modelConverter'],
  modelConverter: ['type', ModelConverter]
};
