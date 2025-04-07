(function (Drupal, drupalSettings) {

  'use strict';

  /**
   * Initialize Klaro! with custom settings.
   */
  Drupal.behaviors.klaro = {
    config: false,
    manager: false,
    observing: false,
    needCallBehaviors: false,
    ready: false,
    attach: function (context, settings) {
      if (!settings.klaro) {
        return;
      }
      if (typeof klaro === 'undefined') {
        document.querySelector('#klaro-js').addEventListener("load", () => {
          Drupal.behaviors.klaro.proceed(context, settings);
        });
      }
      else {
        Drupal.behaviors.klaro.proceed(context, settings);
      }
    },
    proceed: function(context, settings) {
      // Bootstrap config once.
      if (!Drupal.behaviors.klaro.config) {
        settings.klaro.config.services.forEach(function (service, a_index) {
          // Set callback for each service.
          settings.klaro.config.services[a_index].callback = Drupal.behaviors.klaro.serviceCallback;
          // Create regular expressions from config.
          service.cookies.forEach(function (cookie, c_index) {
            settings.klaro.config.services[a_index]['cookies'][c_index] = [new RegExp(cookie[0], 'i'), cookie[1] || '/', cookie[2] || location.host];
          });
        });

        Drupal.behaviors.klaro.config = settings.klaro.config;
      }

      // Decorate wrapperIdentifiers in context.
      Drupal.behaviors.klaro.klaroDecorateWrapper(context, settings);

      // Store reference to manager once.
      if (!Drupal.behaviors.klaro.manager) {
        Drupal.behaviors.klaro.manager = klaro.getManager(Drupal.behaviors.klaro.config);
      }

      // Loading klaro less intrusive.
      if (settings.klaro.dialog_mode === 'silent') {
        Drupal.behaviors.klaro.manager.confirmed = true;
      }

      // Setup klaro on each attach (support ajax insert commands).
      klaro.setup(Drupal.behaviors.klaro.config);

      // Observe klaro-element to add aria-features.
      Drupal.behaviors.klaro.klaroElementObserver();

      // Add title to learn more link.
      let label_open_consent_dialog = Drupal.t("Open consent dialog", {},{context: 'klaro'});
      document.querySelector('a.cm-link.cn-learn-more')?.setAttribute('title', label_open_consent_dialog);

      // Store reference to manager once.
      if (!Drupal.behaviors.klaro.manager) {
        Drupal.behaviors.klaro.manager = klaro.getManager(Drupal.behaviors.klaro.config);
      }

      if(context === document) {
        var elements = once('klaro', 'body', context);
        Array.prototype.forEach.call(elements, function () {
          // Add toggle dialog button.
          if (settings.klaro.show_toggle_button && !document.getElementById('klaro_toggle_dialog')) {
            var button_label = Drupal.t("Manage consents", {},{context: 'klaro'});
            var button_html = '<button id="klaro_toggle_dialog" aria-label="' + button_label + '" aria-haspopup="dialog" title="'+ button_label + '" type="button" class="klaro_toggle_dialog klaro_toggle_dialog_override" rel="open-consent-manager"></button>';
            document.body.insertAdjacentHTML('afterbegin', button_html);
          }
        });
      }
      else {
        for (var app in Drupal.behaviors.klaro.manager.executedOnce) {
          if (Drupal.behaviors.klaro.manager.executedOnce[app]) {
            // (Re-)apply consents on each attach (ajax add_js support)
            // only if there are elements with data-src left.
            if (document.querySelectorAll('[data-name="' + app + '"][data-src]:not([src])').length > 0) {
              Drupal.behaviors.klaro.manager.applyConsents();
            }
          }
        }
      }

      // Set preview image for contextual consent.
      var elements = once('klaro-thumbnail', '.klaro.cm-as-context-notice', context);
      Array.prototype.forEach.call(elements, function (el) {
        let klaro_elem = (el.closest('.field'))?.querySelector('[data-modified-by-klaro]');
        let thumbnail = klaro_elem?.getAttribute('data-thumbnail');
        let title = klaro_elem?.getAttribute('title');

        if (thumbnail) {
          el.parentElement.style.backgroundImage = 'url(' + thumbnail + ')';
          el.parentElement.style.backgroundSize = 'cover';
          el.parentElement.style.backgroundPosition = 'center';
          el.firstChild.style.backgroundColor = 'rgba(250, 250, 250, 0.75)';
        }

        if (title) {
          let title_elem = document.createElement('p');
          title_elem.innerHTML = title;
          el.firstChild.prepend(title_elem);
        }
      });

      // Iterate services and replace placeholder texts.
      Drupal.behaviors.klaro.config.services.forEach(function(service, a_index) {
        if (service.contextualConsentText) {
          var elements = once('klaro-text', '[data-type="placeholder"][data-name="' + service.name + '"] div.context-notice > p:first-child', context);
          if (elements.length > 0) {
            let title_elem = document.createElement('p');
            title_elem.innerHTML = service.contextualConsentText;
            let el = elements[0];
            el.parentNode.replaceChild(title_elem, el);
          }
        }
      });

      // Add link to consent manager for contextual consents.
      var elements = once('klaro-consent-link', '[data-type="placeholder"] div.context-notice');
      var title = Drupal.t("Open the Consent Management Dialog", {}, {context: 'klaro'});
      var linktext = Drupal.t("Manage privacy settings", {}, {context: 'klaro'});
      Array.prototype.forEach.call(elements, function (el) {
        el.insertAdjacentHTML('beforeend', `<p class="cm-dialog-link"><a href="#" title="${title}" rel="open-consent-manager">${linktext}</a></p>`);
      });

      // Call Behaviors if needed.
      if (Drupal.behaviors.klaro.needCallBehaviors && context === document) {
        Drupal.behaviors.klaro.needCallBehaviors = false;
        Drupal.attachBehaviors(document, drupalSettings);
      }

      // Set ready.
      Drupal.behaviors.klaro.ready = true;
    },

    /**
     * A callback for each service that will run at the beginning and on consent change.
     */
    serviceCallback: function (consent, service) {

      // If onlyOnce, abort if already executed.
      if (Drupal.behaviors.klaro.manager && service.onlyOnce && Drupal.behaviors.klaro.manager.executedOnce[service.name]) {
        return;
      }

      // Execute callbackCode from config form.
      if (service.callbackCode.length > 0) {
        Function('consent', 'service', service.callbackCode)(consent, service);
      }

      // Do the behaviors only for consent.
      if (!consent) {
        return;
      }

      // Rewrite data-id to id.
      var elements = document.querySelectorAll('[data-name="' + service.name + '"][data-id]');
      if (elements.length > 0) {
        Array.prototype.forEach.call(elements, function(element) {
          element.setAttribute('id', element.getAttribute('data-id'));
        });
        if (Drupal.behaviors.klaro.ready) {
          Drupal.attachBehaviors(document, drupalSettings);
        }
        else {
          Drupal.behaviors.klaro.needCallBehaviors = true;
        }
      }

      let knownBehaviors = Object.keys(Drupal.behaviors);
      var elements = document.querySelectorAll('[data-name="' + service.name + '"][src]');
      if (!elements) {
        return;
      }

      // #3459261 Fix HtmlMediaElements not loading correctly.
      var source_tags = document.querySelectorAll('source[data-name="' + service.name + '"]');
      if (source_tags.length > 0) {
        var loading_state;
        for (var i = 0; i < source_tags.length; i++) {
          loading_state = source_tags[i].parentElement.readyState;
          if (loading_state == 0 || loading_state == 1) {
            source_tags[i].parentElement.load();
          }
        }
      }

      function processBehaviors() {
        for (let name in Drupal.behaviors) {
          if (knownBehaviors.indexOf(name) === -1) {
            // Execute newly added behaviors. This is somewhat guesswork, as
            // the order of execution is undetermined based on file load order.
            Drupal.behaviors[name].attach(document, drupalSettings);
            knownBehaviors.push(name);
          }
        }
      }

      Array.prototype.forEach.call(elements, function(element) {
        if (element.complete) {
          processBehaviors();
        }
        else {
          element.addEventListener('load', processBehaviors);
        }
      });
    },

    /**
     * Decorate wrapperIdentifiers in context.
     */
    klaroDecorateWrapper: function(context, settings) {
      // Iterate services and wrapperIdentifier.
      settings.klaro.config.services.forEach(function(service, a_index) {
        service.wrapperIdentifier.forEach(function(wrapperIdentifier) {
          var elements = once('klaro-wrap', wrapperIdentifier, context);
          Array.prototype.forEach.call(elements, function(el) {
            if (el) {
              var wrapper = document.createElement('div');
              wrapper.setAttribute('data-name', service.name);
              wrapper.setAttribute('data-original-display', getComputedStyle(el).display);
              el.parentNode.insertBefore(wrapper, el);
              wrapper.appendChild(el);
            }
          });
        });
      });
    },

    /**
     * Watch for DOMchanges on klaro-element.
     */
    klaroElementObserver: function() {
      var targetNode = document.getElementById("klaro");
      if (Drupal.behaviors.klaro.observing || !targetNode) {
        return;
      }
      Drupal.behaviors.klaro.observing = true;

      const config = { childList: true, subtree: true };
      const callback = (mutationList, observer) => {
        for (const mutation of mutationList) {
          if (mutation.type === "childList") {
            Drupal.behaviors.klaro.klaroElementMutator();
          }
        }
      };
      // Create an observer instance linked to the callback function
      const observer = new MutationObserver(callback);
      observer.observe(targetNode, config);
      Drupal.behaviors.klaro.klaroElementMutator();
    },

    /**
     * Observer klaro element to adapt customizations.
     */
    klaroElementMutator: function() {

      // Add minimal accessibility features to the customize consent dialog.
      var labels = document.querySelectorAll('#klaro label')
      if (labels.length > 0){
        for (var i = 0; i < labels.length; i++) {
          labels[i].setAttribute('onkeydown', 'Drupal.behaviors.klaro.KlaroToggleService(event)');
          labels[i].setAttribute('aria-labelledby', labels[i].getAttribute('for') + '-title');
          labels[i].setAttribute('aria-describedby', labels[i].getAttribute('for') + '-description');
          if (labels[i].previousElementSibling && labels[i].previousElementSibling.tagName == 'INPUT') {
            labels[i].previousElementSibling.setAttribute('tabindex', '0');
            labels[i].removeAttribute('tabindex');
          }
        }
        labels[0].focus();
      }
      // Handle close button X.
      if (drupalSettings.klaro.show_close_button) {
        if (document.querySelector('#klaro-cookie-notice')) {
          var elem = document.querySelector('#klaro-cookie-notice');
        }
        else if (document.querySelector('.cm-modal.cm-klaro')) {
          var elem = document.querySelector('.cm-modal.cm-klaro');
          if ((Drupal.behaviors.klaro.manager.confirmed) && (!Drupal.behaviors.klaro.config.mustConsent)) {
            elem = false;
          }
        }

        if (elem && !document.querySelector('.klaro-close')) {
          var close_label = Drupal.t("Close dialog and decline all", {},{context: 'klaro'});
          var close_html = '<button title="' + close_label + '" type="button" class="cn-decline klaro-close" tabindex="0" aria-label="' + close_label + '"></button>';
          elem.insertAdjacentHTML('beforeend', close_html);;
          document.querySelector('.klaro-close')?.addEventListener('click', (e) => {
            Drupal.behaviors.klaro.manager.changeAll(false);
            Drupal.behaviors.klaro.manager.saveAndApplyConsents();
          }, false);
          document.querySelector('.klaro').classList.add('klaro-close-enabled');
          document.querySelector('.klaro .cookie-modal .cm-modal .hide')?.remove();
        }
      }

    },
    KlaroToggleService: function(event) {
      if (event.key === "Enter") {
        event.target.click();
        event.target.focus();
      }
    }
  };

  /**
   * Open the Klaro! consent notice from links & buttons.
   */
  Drupal.behaviors.klaroLink = {
    attach: function (context, settings) {
      var elements = once('klaroLink', '[rel*="open-consent-manager"]', context);
      Array.prototype.forEach.call(elements, function(element) {
        element.addEventListener('click', function (event) {
          klaro.show(Drupal.behaviors.klaro.config);
          event.preventDefault();
          return false;
        });
      });
    }
  };
})(Drupal, drupalSettings);
