import BpmnModeler from 'bpmn-js/lib/Modeler';
import {
    BpmnPropertiesPanelModule,
    BpmnPropertiesProviderModule,
    CamundaPlatformPropertiesProviderModule,
} from 'bpmn-js-properties-panel';
import {
  ElementTemplatesPropertiesProviderModule,
} from 'bpmn-js-element-templates';
import CamundaBpmnModdle from 'camunda-bpmn-moddle/resources/camunda.json'
import ElementTemplateChooserModule from '@bpmn-io/element-template-chooser';
import { layoutProcess } from 'bpmn-auto-layout';
import ModelConverter from './ModelConverter';
import { expectToBeAccessible } from '@bpmn-io/a11y';

window.modeller = new BpmnModeler({
    container: '#bpmn-io .canvas',
    propertiesPanel: {
        parent: '#bpmn-io .property-panel'
    },
    additionalModules: [
        BpmnPropertiesPanelModule,
        BpmnPropertiesProviderModule,
        CamundaPlatformPropertiesProviderModule,
        ElementTemplatesPropertiesProviderModule,
        ElementTemplateChooserModule,
        ModelConverter,
    ],
    moddleExtensions: {
        camunda: CamundaBpmnModdle
    },
    elementTemplates: [],
  });

window.layoutProcess = layoutProcess;
window.expectToBeAccessible = expectToBeAccessible;
