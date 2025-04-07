(function ($, Drupal, drupalSettings) {

  Drupal.bpmn_io = {};

  Drupal.behaviors.bpmn_io = {
    attach: function (context, settings) {
      if (Drupal.bpmn_io.modeller === undefined) {
        window.addEventListener('resize', function (event) {
          let container = $('#bpmn-io');
          let offset = container.offset();
          let width = container.width();
          $('#bpmn-io .canvas')
            .css('top', offset.top)
            .css('left', offset.left)
            .css('width', width);
          $('#bpmn-io .property-panel')
            .css('max-height', $(window).height() - offset.top);
        }, false);
        window.dispatchEvent(new Event('resize'));

        let sidebar = document.getElementById('gin_sidebar');
        if (sidebar !== null) {
          new MutationObserver(function (mutations) {
            mutations.forEach(function(mutation) {
              window.dispatchEvent(new Event('resize'));
            });
          }).observe($('body')[0], {
            attributes: true
          });
          new ResizeObserver(function () {
            window.dispatchEvent(new Event('resize'));
          }).observe(sidebar);
        }

        Drupal.bpmn_io.modeller = window.modeller;
        Drupal.bpmn_io.layoutProcess = window.layoutProcess;
        Drupal.bpmn_io.expectToBeAccessible = window.expectToBeAccessible;
        Drupal.bpmn_io.expectToBeAccessible($('#bpmn-io .canvas'));
        Drupal.bpmn_io.loader = Drupal.bpmn_io.modeller.get('elementTemplatesLoader');
        Drupal.bpmn_io.loader.setTemplates(settings.bpmn_io.templates);
        Drupal.bpmn_io.open(settings.bpmn_io.bpmn, !settings.bpmn_io.isnew);
        $('form input.button.eca-save').click(function () {
          Drupal.bpmn_io.export();
          return false;
        });
        $('form input.button.eca-export-svg').click(function () {
          Drupal.bpmn_io.saveSVG();
          return false;
        });
        $('form input.button.eca-layout-process').click(function () {
          Drupal.bpmn_io.autoLayout();
          return false;
        });
        $('form input.button.eca-close').click(function () {
          window.location = drupalSettings.bpmn_io.collection_url;
          return false;
        });
        Drupal.bpmn_io.dragAndDrop($('#bpmn-io .property-panel.in-canvas')[0]);
      }
      Drupal.bpmn_io.prepareMessages();
    },
  };

  Drupal.bpmn_io.prepareMessages = function () {
    $('.messages-list:not(.bpmn-io-processed)')
      .addClass('bpmn-io-processed')
      .click(function () {
        $(this).empty();
      });
  };

  Drupal.bpmn_io.export = async function () {
    $('.messages-list').empty();
    let result = await Drupal.bpmn_io.modeller.saveXML({ format: true });
    let request = Drupal.ajax({
      url: drupalSettings.bpmn_io.save_url,
      submit: result.xml,
      progress: {
        type: 'fullscreen',
        message: Drupal.t('Saving model...'),
      },
    });
    request.execute();
    Drupal.bpmn_io.prepareMessages();
  };

  Drupal.bpmn_io.open = async function (bpmnXML, readOnlyId) {
    await Drupal.bpmn_io.modeller.importXML(bpmnXML);
    Drupal.bpmn_io.canvas = Drupal.bpmn_io.modeller.get('canvas');
    Drupal.bpmn_io.overlays = Drupal.bpmn_io.modeller.get('overlays');
    Drupal.bpmn_io.canvas.zoom('fit-viewport');
    if (readOnlyId) {
      let idField = $('#bio-properties-panel-id');
      let modelId = $(idField)[0].value;
      let eventBus = Drupal.bpmn_io.modeller.get('eventBus');
      eventBus.on('element.click', function (e) {
        if (e.element.id === modelId) {
          $(idField)
            .hide()
            .parent('.bio-properties-panel-textfield').find('label span').show();
        } else {
          $(idField)
            .show()
            .parent('.bio-properties-panel-textfield').find('label span').hide();
        }
      });
      $(idField)
        .hide()
        .parent('.bio-properties-panel-textfield').find('label').append('<span>: ' + modelId + '</span>');
    }
  };

  Drupal.bpmn_io.dragAndDrop = function (panel) {
    if (panel === undefined) {
      return;
    }
    const BORDER_SIZE = 4;
    let m_pos;

    function resize(e) {
      const dx = m_pos - e.x;
      m_pos = e.x;
      panel.style.width = (parseInt($(panel).outerWidth()) - BORDER_SIZE + dx) + 'px';
    }

    panel.addEventListener('mousedown', function (e) {
      if (e.offsetX < BORDER_SIZE) {
        m_pos = e.x;
        document.addEventListener('mousemove', resize, false);
      }
    }, false);
    document.addEventListener('mouseup', function () {
      document.removeEventListener('mousemove', resize, false);
    }, false);
  };

  Drupal.bpmn_io.saveSVG = function () {
    Drupal.bpmn_io.canvas.focus();
    Drupal.bpmn_io.modeller.saveSVG({ format: true }).then((model) => {
      let svgBlob = new Blob([model.svg], {
        type: 'image/svg+xml'
      });
      let downloadLink = document.createElement('a');
      downloadLink.download = drupalSettings.bpmn_io.id + '.svg';
      downloadLink.innerHTML = 'Get BPMN SVG';
      downloadLink.href = window.URL.createObjectURL(svgBlob);
      downloadLink.onclick = function (event) {
        document.body.removeChild(event.target);
      };
      downloadLink.style.visibility = 'hidden';
      document.body.appendChild(downloadLink);
      downloadLink.click();
    });
  };

  Drupal.bpmn_io.autoLayout = async function () {
    // Export and auto-layout the model.
    const result = await Drupal.bpmn_io.modeller.saveXML({ format: true });
    const diagramWithLayoutXML = await Drupal.bpmn_io.layoutProcess(result.xml);

    Drupal.bpmn_io.open(diagramWithLayoutXML, !drupalSettings.bpmn_io.isnew);
    Drupal.ginStickyFormActions?.hideMoreActions();
  };

})(jQuery, Drupal, drupalSettings);
