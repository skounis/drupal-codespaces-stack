(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.aiAgentsExplorer = {
    writtenMessage: {},
    timer: 0,
    attach: (context) => {
      // Take over the button and runner.
      $('#edit-submit').off('click').on('click', (e) => {
        e.preventDefault();
        // Check so that a text and an agent is chosen.
        if ($('#edit-agent').val() == '' || $('#edit-prompt').val() == '' || $('#edit-model').val() == '') {
          alert(Drupal.t('Please fill in a prompt and choose an agent and model.'));
          return;
        }
        // Start the runner.
        startRunner();
      });
    }
  };

  function startRunner() {
    // Reset table.
    $('.explorer-messages tbody').html('<tr><th>' + Drupal.t('Step') + '</th><th>' + Drupal.t('Time from start (s)') + '</th></tr><tr><td>' + Drupal.t('Starting') + '</td><td>0</td></tr>');
    // Reset written messages and timer.
    Drupal.behaviors.aiAgentsExplorer.writtenMessage = {};
    Drupal.behaviors.aiAgentsExplorer.timer = 0;
    // Disable button.
    $('#edit-submit').val(Drupal.t('Running...')).attr('disabled', 'disabled');
    // Create a unique id for the runner.
    let uuid = uuidv4();
    // Run directly to get microtime.
    pollRunner(uuid);
    let pollInterval = setInterval(() => {
      pollRunner(uuid);
    }, 500)
    //$('#edit-submit').val(Drupal.t('Run Agent')).removeAttr('disabled');
    let files = [];
    $('.form-type--managed-file .form-item input').each((index, element) => {
      let id = element.name.replace('image[file_', '').replace('][selected]', '');
      if (id) {
        files.push(id);
      }
    });
    $.ajax({
      url: drupalSettings.path.baseUrl + 'admin/config/ai/agents/explore/start',
      type: 'POST',
      data: {
        agent: $('#edit-agent').val(),
        prompt: $('#edit-prompt').val(),
        images: files,
        model: $('#edit-model').val(),
        runner_id: uuid,
      },
      success: function (response) {
        setTimeout(() => {
          clearInterval(pollInterval);
          let newTime = response.time - Drupal.behaviors.aiAgentsExplorer.timer;
          newTime = Math.round(newTime * 100) / 100;
          let color = response.success ? '' : ' style="background: #ffaaaa;"'
          $('.explorer-messages tbody').append('<tr ' + color + '><td>' + Drupal.t('Final Answer') + '<br><details><summary>' + Drupal.t('Response') + '</summary><pre>' + response.message + '</pre></td><td>' + newTime + '</td></tr>');
        }, 1000);

        $('#edit-submit').val(Drupal.t('Run Agent')).removeAttr('disabled');
      },
      error: function (xhr) {
        let response = JSON.parse(xhr.responseText);
        setTimeout(() => {
          clearInterval(pollInterval);
          let newTime = response.time - Drupal.behaviors.aiAgentsExplorer.timer;
          newTime = Math.round(newTime * 100) / 100;
          $('.explorer-messages tbody').append('<tr style="background: #ffaaaa;"><td>' + Drupal.t('Final Answer') + '<br><details><summary>' + Drupal.t('Response') + '</summary><pre>Error: ' + response.message + '</pre></td><td>' + newTime + '</td></tr>');
        }, 1000);
        $('#edit-submit').val(Drupal.t('Run Agent')).removeAttr('disabled');
      }
    });

  }

  function pollRunner(runner_id) {
    $.getJSON({
      url: drupalSettings.path.baseUrl + 'admin/config/ai/agents/explore/poll/' + runner_id,
      success: function (response) {
        for (let x in response.entries) {
          // If timer is not set, set it.
          if (Drupal.behaviors.aiAgentsExplorer.timer === 0) {
            Drupal.behaviors.aiAgentsExplorer.timer = (response.time - 0.5);
          }
          if (Drupal.behaviors.aiAgentsExplorer.writtenMessage[response.entries[x].id] === undefined) {
            Drupal.behaviors.aiAgentsExplorer.writtenMessage[response.entries[x].id] = response.entries[x];
            let newTime = response.entries[x].created - Drupal.behaviors.aiAgentsExplorer.timer;
            newTime = Math.round(newTime * 100) / 100;
            $('.explorer-messages tbody').append('<tr><td><a href="' + drupalSettings.path.baseUrl + 'ai-agent-decision/' + response.entries[x].id + '" target="_blank">' + response.entries[x].label + '</a><br><details><summary>' + Drupal.t('Response') + '</summary><pre>' + response.entries[x].json + '</pre></td><td>' + newTime + '</td></tr>');
          }
        }
      }
    });
  }

  function uuidv4() {
    return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, c =>
      (+c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> +c / 4).toString(16)
    );
  }
})(jQuery, Drupal, drupalSettings);

