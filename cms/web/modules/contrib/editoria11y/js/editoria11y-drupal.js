/**
 * Drupal initializer.
 * Launch as behavior and pull variables from config.
 */

  // Prevent multiple inits in modules that re-trigger the document context.
let ed11yOnce;
let ed11yInitialized;
let ed11yWaiting = false;

const ed11yInitializer = function () {
  /**
   * Initiate library
   *
   * */

  if (ed11yInitialized === 'disabled' ||
    ed11yInitialized === 'pending') {
    return;
  }
  ed11yInitialized = 'pending';

  let options = {};

  // todo postpone: store dismissalKeys for PDFs in page results, and check dismissals table for page level matches on load.

  options.checkRoots = drupalSettings.editoria11y.content_root ?
    drupalSettings.editoria11y.content_root :
    false;
  options.ignoreElements = !!drupalSettings.editoria11y.ignore_elements ?
    `#toolbar-administration *, ${drupalSettings.editoria11y.ignore_elements}` :
    '#toolbar-administration *';
  options.panelNoCover = !!drupalSettings.editoria11y.panel_no_cover ?
    drupalSettings.editoria11y.panel_no_cover :
    '#klaro-cookie-notice, #klaro_toggle_dialog, .same-page-preview-dialog.ui-dialog-position-side, #gin_sidebar';
  options.ignoreAllIfAbsent = !!drupalSettings.editoria11y.ignore_all_if_absent ?
    drupalSettings.editoria11y.ignore_all_if_absent :
    false;
  options.buttonZIndex = 491; // 99999
  options.autoDetectShadowComponents = !!drupalSettings.editoria11y.detect_shadow;
  options.shadowComponents = drupalSettings.editoria11y.shadow_components ? drupalSettings.editoria11y.shadow_components : false;
  options.watchForChanges = drupalSettings.editoria11y.watch_for_changes === 'checkRoots' ?
    'checkRoots' :
    drupalSettings.editoria11y.watch_for_changes === 'true';

  const editors = (Drupal.editors && (Object.hasOwn(Drupal.editors, 'ckeditor5') || Object.hasOwn(Drupal.editors, 'gutenberg')));

  let delay = drupalSettings.path.currentPathIsAdmin ? 250 : 0;
  // Way too many race conditions on admin side.
  if (document.URL.indexOf('mode=same_page_preview') > -1) {
    // todo: would need an web worker dance like WP to show issue highlights.
    ed11yOnce = true;
    ed11yInitialized = 'disabled';
    return;
  } else if (drupalSettings.path.currentPathIsAdmin &&
    (!!drupalSettings.editoria11y.disable_live || !editors)
  ) {
    // Ed11y will init later if a behavior brings in something editable.
    ed11yInitialized = false;
    return;
  }

  if (document.querySelector('.layout-builder-form')) {
    // Layout builder checking currently disabled; it breaks scroll.
    ed11yOnce = true;
    ed11yInitialized = 'disabled';
    return;

  } else if (editors) {
    // Editable content is present.
    options.buttonZIndex = 99999;
    options.autoDetectShadowComponents = false;
    // But we don't try to check live inside layout builder.
    if (drupalSettings.path.currentPathIsAdmin &&
      !!drupalSettings.editoria11y.disable_live) {
      // Don't disable in frontend for comment fields.
      ed11yOnce = true;
      ed11yInitialized = 'disabled';
      options.watchForChanges = true;
      return;
    }
    options.inlineAlerts = false;
    if (Object.hasOwn(Drupal.editors, 'gutenberg')) {
      options.ignoreAriaOnElements = 'h1,h2,h3,h4,h5,h6';
      delay = 1000;
      window.setTimeout(function () {
        if (Ed11y.results.length === 0) {
          // Ed11y fails to initialize if Gutenberg is really late.
          Ed11y.checkAll();
        }
      }, 6000);
    }
    options.checkRoots = '.gutenberg__editor .is-root-container, [contenteditable="true"]:not(.gutenberg__editor [contenteditable], [contenteditable="true"] [contenteditable])';
    options.ignoreElements += ', [hidden], [style*="display: none"], [style*="display:none"], [hidden] *, [style*="display: none"] *, [style*="display:none"] *, [data-drupal-message-type]';
    // todo merge
    options.ignoreAllIfAbsent = options.ignoreAllIfAbsent ?
      options.ignoreAllIfAbsent + ', [contenteditable="true"], .gutenberg__editor .is-root-container':
      '[contenteditable="true"], .gutenberg__editor .is-root-container';

    //options.ignoreAllIfAbsent = '[contenteditable="true"], .gutenberg__editor .is-root-container';
    options.editorHeadingLevel = [];
    if (drupalSettings.editoria11y.live_h2) {
      options.editorHeadingLevel.push(
        {
          selector: drupalSettings.editoria11y.live_h2,
          previousHeading: 1,
        }
      );
    }
    if (drupalSettings.editoria11y.live_h3) {
      options.editorHeadingLevel.push(
        {
          selector: drupalSettings.editoria11y.live_h3,
          previousHeading: 2,
        }
      );
    }
    if (drupalSettings.editoria11y.live_h4) {
      options.editorHeadingLevel.push(
        {
          selector: drupalSettings.editoria11y.live_h4,
          previousHeading: 3,
        }
      );
    }
    if (drupalSettings.editoria11y.live_h_inherit) {
      options.editorHeadingLevel.push(
        {
          selector: drupalSettings.editoria11y.live_h_inherit,
          previousHeading: 'inherit',
        }
      );
    }
    options.editorHeadingLevel.push({
      selector: '*',
      previousHeading: 0, // Ignores first heading for level skip detection.
    })
  }

  ed11yOnce = true;

  let urlParams = new URLSearchParams(window.location.search);
  let lang = drupalSettings.editoria11y.lang ? drupalSettings.editoria11y.lang : 'en';

  if (lang !== 'en') {
    lang = 'dynamic';
    ed11yLang.dynamic = ed11yLangDrupal;
    options.langSanitizes = true; // Use Drupal string sanitizer.
  }

  let ed11yAlertMode = drupalSettings.editoria11y.assertiveness ? drupalSettings.editoria11y.assertiveness : 'assertive';
  // If assertiveness is "smart" we set it to assertive if the doc was recently changed.
  const now = new Date();
  if (drupalSettings.path.currentPathIsAdmin && (Drupal.editors && (Object.hasOwn(Drupal.editors, 'ckeditor5') || Object.hasOwn(Drupal.editors, 'gutenberg'))) && drupalSettings.editoria11y.assertiveness !== 'polite') {
    ed11yAlertMode = 'active';
  }
  else if (
    urlParams.has('ed1ref') ||
    (ed11yAlertMode === 'smart' &&
      ((now / 1000) - drupalSettings.editoria11y.changed < 60)
    )
  ) {
    ed11yAlertMode = 'assertive';
  }

  options.lang = lang;
  options.ignoreByKey = {
    'p': 'table:not(.field-multiple-table) p',
    'h': '.filter-guidelines-item *, nav *, [id$="-local-tasks"] *, .block-local-tasks-block *, .tabledrag h4',
    // disable alt text tests on unspoken images
    'img': '[aria-hidden], [aria-hidden] img, a[href][aria-label] img, button[aria-label] img, a[href][aria-labelledby] img, button[aria-labelledby] img',
    // disable link text check on disabled and admin links:
    'a': `[aria-hidden][tabindex], [id$="-local-tasks"] a, .block-local-tasks-block a, .filter-help > a, .contextual-region > nav a ${drupalSettings.path.currentPathIsAdmin ? ', a[target="_blank"]' : ''}`,
    // 'li': false,
    // 'blockquote': false,
    // 'iframe': false,
    // 'audio': false,
    // 'video': false,
    'table': '[role="presentation"], .tabledrag',
    // todo: report h4 and th issue in docroot/core/includes/theme.inc
  };
  options.alertMode = ed11yAlertMode;
  options.currentPage = drupalSettings.editoria11y.page_path;
  options.allowHide = !!drupalSettings.editoria11y.allow_hide;
  options.allowOK = !!drupalSettings.editoria11y.allow_ok;
  options.syncedDismissals = drupalSettings.editoria11y.dismissals;
  options.showDismissed = urlParams.has('ed1ref');
  // todo postpone: ignoreAllIfPresent
  options.preventCheckingIfPresent = !!drupalSettings.editoria11y.no_load ?
    drupalSettings.editoria11y.no_load + '.layout-builder-form' :
    '.layout-builder-form';
  // todo postpone: preventCheckingIfAbsent
  options.linkStringsNewWindows = !!drupalSettings.editoria11y.link_strings_new_windows ?
    new RegExp (drupalSettings.editoria11y.link_strings_new_windows, 'gi')
    : !!drupalSettings.editoria11y.ignore_link_strings ?
      new RegExp(drupalSettings.editoria11y.ignore_link_strings, 'gi')
      : new RegExp ('(' + Drupal.t('download') + ')|(\\s' + Drupal.t('tab') + ')|(' + Drupal.t('window') + ')', 'gi');
  options.linkIgnoreStrings = !!drupalSettings.editoria11y.ignore_link_strings ? new RegExp(drupalSettings.editoria11y.ignore_link_strings, 'gi') : new RegExp('(' + Drupal.t('link is external') + ')|(' + Drupal.t('link sends email') + ')', 'gi');
  options.linkIgnoreSelector = !!drupalSettings.editoria11y.link_ignore_selector ? drupalSettings.editoria11y.link_ignore_selector : false;
  options.hiddenHandlers = !!drupalSettings.editoria11y.hidden_handlers ? drupalSettings.editoria11y.hidden_handlers : '';
  options.constrainButtons = !!drupalSettings.editoria11y.element_hides_overflow ? drupalSettings.editoria11y.element_hides_overflow : '';
  options.theme = !!drupalSettings.editoria11y.theme ? drupalSettings.editoria11y.theme : 'sleekTheme';
  options.embeddedContent = !!drupalSettings.editoria11y.embedded_content_warning ? drupalSettings.editoria11y.embedded_content_warning : false;
  options.documentLinks = !!drupalSettings.editoria11y.download_links ? drupalSettings.editoria11y.download_links : `a[href$='.pdf'], a[href*='.pdf?']`;
  options.customTests = drupalSettings.editoria11y.custom_tests;
  options.cssUrls = !!drupalSettings.editoria11y.css_url ? [drupalSettings.editoria11y.css_url + '/library/css/editoria11y.css'] : false;
  options.ignoreTests = drupalSettings.editoria11y.ignore_tests ? drupalSettings.editoria11y.ignore_tests : false;


  const editSelector = (selector, action) => {
    return `[id$="-local-tasks"] a[href*="/${selector}/"][href$="/${action}"],
            .block-local-tasks-block a[href*="/${selector}/"][href$="/${action}"]`;
  }
  const editLink = document.querySelector(editSelector('node', 'edit'));
  const layoutLink = document.querySelector(editSelector('node', 'layout'));
  const userLink = document.querySelector(editSelector('user', 'edit'));
  const termLink = document.querySelector(editSelector('taxonomy/term', 'edit'));
  if (editLink || layoutLink || userLink || termLink) {
    const editIcon = document.createElement('span');
    editIcon.classList.add('ed11y-custom-edit-icon');
    editIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path fill="currentColor" d="M441 58.9L453.1 71c9.4 9.4 9.4 24.6 0 33.9L424 134.1 377.9 88 407 58.9c9.4-9.4 24.6-9.4 33.9 0zM209.8 256.2L344 121.9 390.1 168 255.8 302.2c-2.9 2.9-6.5 5-10.4 6.1l-58.5 16.7 16.7-58.5c1.1-3.9 3.2-7.5 6.1-10.4zM373.1 25L175.8 222.2c-8.7 8.7-15 19.4-18.3 31.1l-28.6 100c-2.4 8.4-.1 17.4 6.1 23.6s15.2 8.5 23.6 6.1l100-28.6c11.8-3.4 22.5-9.7 31.1-18.3L487 138.9c28.1-28.1 28.1-73.7 0-101.8L474.9 25C446.8-3.1 401.2-3.1 373.1 25zM88 64C39.4 64 0 103.4 0 152L0 424c0 48.6 39.4 88 88 88l272 0c48.6 0 88-39.4 88-88l0-112c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 112c0 22.1-17.9 40-40 40L88 464c-22.1 0-40-17.9-40-40l0-272c0-22.1 17.9-40 40-40l112 0c13.3 0 24-10.7 24-24s-10.7-24-24-24L88 64z"/></svg>';
    const reLink = function(link, text) {
      const linkButton = document.createElement('a');
      linkButton.href = link.href;
      linkButton.textContent = text;
      linkButton.prepend(editIcon.cloneNode(true));
      return linkButton;
    }
    const editLinks = document.createElement('div');
    if (editLink) {
      editLinks.appendChild(reLink(editLink, Drupal.t('Page editor')));
    }
    if (layoutLink) {
      editLinks.appendChild(reLink(layoutLink, Drupal.t('Layout editor')));
    }
    if (userLink) {
      editLinks.appendChild(reLink(userLink, Drupal.t('Edit user')));
    }
    if (termLink) {
      editLinks.appendChild(reLink(termLink, Drupal.t('Edit term')));
    }
    options.editLinks = editLinks;

    // Set listener to hide links on view.
    if (!!drupalSettings.editoria11y.hide_edit_links) {
      document.addEventListener('ed11yPop', e => {
        if (e.detail.result.element.closest(drupalSettings.editoria11y.hide_edit_links)) {
          e.detail.tip.shadowRoot.querySelector('.ed11y-custom-edit-links')?.setAttribute('hidden', '');
        }
      });
    }

  }


  /*let options = {
    // videoContent: 'youtube.com, vimeo.com, yuja.com, panopto.com',
    // audioContent: 'soundcloud.com, simplecast.com, podbean.com, buzzsprout.com, blubrry.com, transistor.fm, fusebox.fm, libsyn.com',
    // dataVizContent: 'datastudio.google.com, tableau',
    // twitterContent: 'twitter-timeline',
    ,
    editableContent: '[contentEditable="true"], #quickedit-entity-toolbar, .layout-builder-form',
    ,
  };*/

  if (!!drupalSettings.editoria11y.view_reports) {
    options.reportsURL = drupalSettings.editoria11y.dashboard_url;
  }

  if (typeof editoria11yOptionsOverride !== 'undefined' && typeof editoria11yOptions === 'function') {
    options = editoria11yOptions(options);
  }

  ed11yWaiting = true;
  window.setTimeout(function() {
    ed11yInitialized = true;
    const ed11y = new Ed11y(options);
    ed11yWaiting = false;
    // todo: Remove once confirmed that new library listeners cover this.
    // Listen for events that may modify content without triggering a mutation.
    /*window.addEventListener('keyup', (e) => {
      if (Ed11y.bodyStyle &&
        !e.target.closest('.ed11y-element, .ed11y-wrapper, [contenteditable="true"]')) {
        // Arrow changes of radio and select controls.
        Ed11y.incrementalAlign(); // Immediately realign tips.
        Ed11y.alignPending = false;
      }
    });*/
    /*window.addEventListener('click', (e) => {
      // Click covers mouse, keyboard and touch.
      console.log(`Drupal query ignored due to Ed11y click target: ${!!e.target.closest('.ed11y-element, .ed11y-wrapper, [contenteditable="true"]')}`);
      console.log(e.target);
      if (Ed11y.bodyStyle &&
        Ed11y.options.watchForChanges &&
        !e.target.closest('.ed11y-element, .ed11y-wrapper, [contenteditable="true"]')
      ) {
        console.log('drupal click listener');
        Ed11y.incrementalAlign(); // Immediately realign tips.
        Ed11y.alignPending = false;
        Ed11y.incrementalCheck();
      }
    });*/
    window.setTimeout(function() {
      // Append ?ed1string to URLs to check translations
      if (!urlParams.has('ed1strings')) {
        return;
      }
      if (typeof(drupalTranslations) === 'undefined') {
        console.warn('Editoria11y: No translations present to debug.')
        return;
      }
      const target = document.querySelector('main');
      const wrap = document.createElement('div');
      target.prepend(wrap);
      const missingTranslations = document.createElement('strong');
      missingTranslations.textContent = Drupal.t("Translation needed: ");
      missingTranslations.style.setProperty('border', '1px solid');
      missingTranslations.style.setProperty('filter', 'invert(1)');
      for (const [key, value] of Object.entries(Ed11y.M)) {
        if (!ed11yLangDrupal[key]) {
          console.warn(key);
        }
        let checkTranslation = true;
        if (!(drupalTranslations && drupalTranslations.strings && drupalTranslations.problems)) {
          checkTranslation = false;
        }
        let item = document.createElement('div');
        if (value.title && typeof value.tip()) {
          item.textContent = value.tip('example');
          if (checkTranslation && !drupalTranslations.strings[""][value.tip('')]) {
            item.prepend(missingTranslations.cloneNode(true));
          }
          let title = document.createElement('strong');
          title.style.setProperty('display', 'block');
          title.textContent = value.title;
          if (checkTranslation && !drupalTranslations.strings[""][value.title]) {
            title.prepend(missingTranslations.cloneNode(true));
          }
          item.prepend(title);
        } else {
          item.innerHTML = value;
          if (checkTranslation && !(drupalTranslations.strings[""][value] || drupalTranslations.strings['problems'][value])) {
            item.prepend(missingTranslations.cloneNode(true));
          }
        }
        const itemKey = document.createElement('em');
        itemKey.textContent = key  + ': ';
        item.prepend(itemKey);
        wrap.append(item);
        const br = document.createElement('br');
        wrap.append(br);
      }

    },100)
  }, delay);

  /**
   * Initiate sync
   *
   * */

  let csrfToken = false;
  function getCsrfToken(action, data)
  {
    {
      fetch(`${drupalSettings.editoria11y.session_url}`, {
        method: "GET"
      })
        .then(res => res.text())
        .then(token => {
          csrfToken = token;
          postData(action, data).catch(err => console.error(err));
        })
        .catch(err => console.error(err));
    }
  }

  let postData = async function (action, data) {
    if (!csrfToken) {
      getCsrfToken(action, data);
    } else {
      let apiRoot = drupalSettings.editoria11y.api_url.replace('results/report','');
      let url = `${apiRoot}${action}`;
      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify(data),
      })
        .catch((error) => console.error('Error:', error))
    }
  }

  // Purge changed aliases & deleted pages.
  if (urlParams.has('ed1ref') && urlParams.get('ed1ref') !== drupalSettings.editoria11y.page_path) {
    let data = {
      page_path: urlParams.get('ed1ref'),
    };
    window.setTimeout(function() {
      postData('purge/page', data);
    },0,data);
  }

  let results = {};
  let oks = {};
  let total = 0;
  let extractResults = function () {
    results = {};
    oks = {};
    total = 0;
    Ed11y.results.forEach(result => {
      if (result.dismissalStatus !== "ok") {
        // log all items not marked as OK
        let testName = result.test;
        testName = Ed11y.M[testName].title;
        if (results[testName]) {
          results[testName] = parseInt(results[testName]) + 1;
          total++;
        } else {
          results[testName] = 1;
          total++;
        }
      }
      if (result.dismissalStatus === "ok") {
        if (!results[result.test]) {
          oks[result.test] = Ed11y.M[result.test].title;
        }
      }
    })
  }

  let sendResults = function () {
    window.setTimeout(function () {
      total = 0;
      extractResults();
      let url = window.location.pathname + window.location.search;
      url = url.length > 1000 ? url.substring(0, 1000) : url;
      let data = {
        page_title: drupalSettings.editoria11y.page_title,
        page_path: drupalSettings.editoria11y.page_path,
        entity_id: drupalSettings.editoria11y.entity_id,
        page_count: total,
        language: drupalSettings.editoria11y.lang,
        entity_type: drupalSettings.editoria11y.entity_type, // node or false
        route_name: drupalSettings.editoria11y.route_name, // e.g., entity.node.canonical or view.frontpage.page_1
        results: results,
        oks: oks,
        page_url: url,
      };
      postData('results/report', data);
      // Short timeout to let execution queue clear.
    }, 100)
  }

  let firstRun = true;
  if (drupalSettings.editoria11y.dismissals && drupalSettings.editoria11y.sync !== 'dismissals' && drupalSettings.editoria11y.sync !== 'disable') {
    document.addEventListener('ed11yResults', function () {
      if (firstRun) {
        sendResults();
        firstRun = false;
      }
    });
  }

  let ed11yDismissalsCache = {};

  let sendDismissal = function (detail) {
    if (!!detail) {
      let data = {};
      if (detail.dismissAction === 'reset') {
        ed11yDismissalsCache = {};
        data = {
          page_path: drupalSettings.editoria11y.page_path,
          language: drupalSettings.editoria11y.lang,
          route_name: drupalSettings.editoria11y.route_name,
          dismissal_status: 'reset', // ok, ignore or reset
        };
        if (drupalSettings.editoria11y.sync !== 'dismissals') {
          window.setTimeout(function() {
            sendResults();
          },100);
        }
      } else if (detail.dismissTest in ed11yDismissalsCache && ed11yDismissalsCache[detail.dismissTest].includes(detail.dismissKey)) {
        return false;
      } else {
        // Send if we have not already sent the same key.
        // Prevents repeatedly sending during batch dismissal.
        if (!(detail.dismissTest in ed11yDismissalsCache)) {
          ed11yDismissalsCache[detail.dismissTest] = [detail.dismissKey];
        } else {
          ed11yDismissalsCache[detail.dismissTest].push(detail.dismissKey);
        }
        data = {
          page_title: drupalSettings.editoria11y.page_title,
          page_path: drupalSettings.editoria11y.page_path,
          entity_id: drupalSettings.editoria11y.entity_id,
          language: drupalSettings.editoria11y.lang,
          entity_type: drupalSettings.editoria11y.entity_type, // node or false
          route_name: drupalSettings.editoria11y.route_name, // e.g., entity.node.canonical or view.frontpage.page_1
          result_name: Ed11y.M[detail.dismissTest].title, // which test is sending a result
          result_key: detail.dismissTest, // which test is sending a result
          element_id: detail.dismissKey, // some recognizable attribute of the item marked
          dismissal_status: detail.dismissAction, // ok, ignore or reset
        };
        if (detail.dismissAction === 'ok' && drupalSettings.editoria11y.sync !== 'dismissals') {
          window.setTimeout(function() {
            sendResults();
          },100);
        }
      }
      postData('dismiss/' + detail.dismissAction, data);
    }
  }
  if (drupalSettings.editoria11y.dismissals && drupalSettings.editoria11y.sync !== 'disable') {
    document.addEventListener('ed11yDismissalUpdate', function (e) {
      sendDismissal(e.detail)}, false);
  }
}

Drupal.behaviors.editoria11y = {
  attach: function (context, settings) {

    if (ed11yInitialized === true && ed11yOnce) {
      // Recheck page about a second after every behavior.
      // Todo: global mutation watch instead or in addition?
      window.setTimeout(function () {
        Ed11y.forceFullCheck = true;
        if (drupalSettings.editor || typeof(DrupalGutenberg) === 'object') {
          Ed11y.options.inlineAlerts = false;
        }
        if (Ed11y.bodyStyle) {
          // todo: shouldn't forceFull make this not necessary?
          Ed11y.incrementalCheck();
        } else {
          Ed11y.checkAll();
        }
      }, 1000);
    } else if (ed11yOnce &&
      (!ed11yInitialized ||
        ed11yInitialized !== 'pending'
      ) &&
      !drupalSettings.editoria11y.disable_live &&
      Drupal.editors &&
      (Object.hasOwn(Drupal.editors, 'ckeditor5') ||
        Object.hasOwn(Drupal.editors, 'gutenberg'))) {
      window.setTimeout(function () {
        if (ed11yInitialized !== true) {
          ed11yInitializer();
        }
      }, 1000);

    }

    if (context === document && !ed11yOnce && CSS.supports('selector(:is(body))')) {
      ed11yOnce = true;
      // Timeout necessary to prevent Paragraphs needing 2 clicks to open.
      window.setTimeout(()=> {
        ed11yInitializer();
      }, 100);
    }
  }
};

