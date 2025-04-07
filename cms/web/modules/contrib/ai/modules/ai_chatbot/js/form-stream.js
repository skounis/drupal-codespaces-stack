(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.searchApiAiStream = {
    attach: (context) => {
      // Set the assistant id.
      $('.chat-form-thread-id').val(drupalSettings.ai_chatbot.thread_id);
      let streamElements = $('[data-ai-ajax]', context);
      // @todo: Move away from once() since its not in core?
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');

        // Set up a key down handler to submit the form on Enter press in textarea.
        form.find('.chat-form-query').on('keydown', (event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.find('.chat-form-send').click();
          }
        });

        // Set up a click handler to submit the form and stream the response back.
        element.click((event) => {
          event.preventDefault();

          const clickedElement = $(event.currentTarget);
          // Get the message from the textarea.
          let message = form.find('.chat-form-query').val();
          renderUserChatMessage(message)
          .then(() => {
            renderBotChatMessage(form, '');
          });
        });
      });
    }
  };

  function renderUserChatMessage(message) {
    let converter = new showdown.Converter();
    return new Promise((resolve, reject) => {
      $.ajax({
        url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton/' + drupalSettings.ai_chatbot.assistant_id + '/' + drupalSettings.ai_chatbot.thread_id + '/user'
      })
      .done((data) => {
        let skeleton = data.skeleton;
        $('.chat-history').append(skeleton);
        $('.chat-history .chat-message:last h5').html(drupalSettings.ai_chatbot.default_username);
        $('.chat-history .chat-message:last > img').attr('src', drupalSettings.ai_chatbot.default_avatar);
        let responseField = $('.chat-history .chat-message:last');
        responseField.find('.chat-message-message').html(converter.makeHtml(message));
        $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
        return resolve();
      })
      .fail(() => {
        return reject();
      });
    });
  }

  function renderBotChatMessage(form, sendMessage = '') {
    let converter = new showdown.Converter({
      disableForced4SpacesIndentedSublists: true,
      tables: true,
      smoothLivePreview: true,
      parseImgDimensions: true
    });
    $.ajax({
      url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton/' + drupalSettings.ai_chatbot.assistant_id + '/' + drupalSettings.ai_chatbot.thread_id + '/assistant'
    })
    .done((data) => {
      let skeleton = data.skeleton;
      let chatHistory = $('.chat-history');
      chatHistory.append(skeleton);
      Drupal.attachBehaviors(chatHistory[0]);

      chatHistory.find('.chat-message:last h5').html(drupalSettings.ai_chatbot.bot_name);
      chatHistory.find('.chat-message:last > img').attr('src', drupalSettings.ai_chatbot.bot_image);
      let responseField = chatHistory.find('.chat-message:last .chat-message-message');
      chatHistory.scrollTop(chatHistory[0].scrollHeight);

      if (sendMessage === '') {
        let postData = form.serializeArray();
        // Check while creating if its HTML or not.
        let isHtml = drupalSettings.ai_chatbot.output_type == 'html';
        $('.chat-form-query').val('');
        $.ajax({
          url: form.attr('action'),
          method: 'POST',
          data: postData,
          xhrFields: {
            onprogress: function (event) {
              responseField.html(isHtml ? event.currentTarget.response : converter.makeHtml(event.currentTarget.response));
              $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
              responseField.parentsUntil('chat-message').parent().addClass('chat-message--complete');
              showHasHistory();
            },
            onended: function (event) {
              responseField.html(isHtml ? event.currentTarget.response : converter.makeHtml(event.currentTarget.response));
              $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
              responseField.parentsUntil('chat-message').parent().addClass('chat-message--complete');
            }
          }
        });
      }
      else {
        $('.chat-message-message').html(converter.makeHtml(sendMessage));
      }
    });
  }

  // Logic for minimizing the chatbot.
  $(document).ready(() => {
    if (drupalSettings.ai_chatbot.output_type === 'markdown') {
      rerenderChatMessages();
    }

    let chatStatus = 'true';
    if (drupalSettings.ai_chatbot.toggle_state == 'remember') {
      chatStatus = localStorage.getItem("livechat.closed");
    }
    else if (drupalSettings.ai_chatbot.toggle_state == 'open') {
      chatStatus = 'false';
    }
    if (chatStatus == 'false') {
      $('#live-chat .chat').show();
    }
    // If its open.
    $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
    $('#live-chat header').click(function() {
      $('.chat').toggle(function () {
        localStorage.setItem("livechat.closed", localStorage.getItem("livechat.closed") == 'true' ? 'false' : 'true');
        $(this).animate({
          display: 'block',
        }, 100, () => {
          // Go to the bottom of the chat history.
          $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
        });
      })
    });

    $('.chat-form-clear-history').click((event) => {
      event.preventDefault();
      clearHistory();
    });

    drupalSettings.ai_chatbot.has_history ? showHasHistory() : hideHasHistory();

    expandTextarea('edit-query');
  });

  function clearHistory() {
    $.ajax({
      url: drupalSettings.path.baseUrl + 'ajax/chatbot/reset-session/' + drupalSettings.ai_chatbot.assistant_id + '/' + drupalSettings.ai_chatbot.thread_id,
      method: 'POST',
      success: (response) => {
        // Set a new thread id.
        drupalSettings.ai_chatbot.thread_id = response.thread_id;
        $('.chat-history').html('');
        renderBotChatMessage($('.chat-history').closest('form'), drupalSettings.ai_chatbot.first_message);
      }
    })
    hideHasHistory();
  }

  function expandTextarea(id) {
    document.getElementById(id).addEventListener('keyup', function () {
      this.style.overflow = 'hidden';
      this.style.height = 0;
      this.style.height = this.scrollHeight + 'px';
    }, false);
  }

  function hideHasHistory() {
    $('.chat-form-clear-history').css('visibility', 'hidden');
  }

  function showHasHistory() {
    $('.chat-form-clear-history').css('visibility', 'visible');
  }

  function rerenderChatMessages() {
    let converter = new showdown.Converter();
    let responses = $('.chat-history .chat-message-message');
    responses.each(function () {
      let message = this.textContent.trim();
      this.innerHTML = converter.makeHtml(message);
    });
  }

})(jQuery, Drupal, drupalSettings);
