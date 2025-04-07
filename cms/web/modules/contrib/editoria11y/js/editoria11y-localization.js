const ed11yLangDrupal = {

  // Main Panel
  toggleAccessibilityTools: Drupal.t("Toggle accessibility tools"),
  toggleDisabled: Drupal.t('No content available for Editoria11y to check.'),
  panelCount0 : Drupal.t("No issues detected.",{},{context: 'problems'}),
  panelCountAllDismissed : Drupal.t("All issues hidden.",{},{context: 'problems'}),
  panelCount1 : Drupal.t("One issue detected.",{},{context: 'problems'}),
  panelCountMultiple: Drupal.t(" issues detected.",{},{context: 'problems'}),
  panelCountBase: `<span class='count'>${Drupal.t("No",{},{context: 'zero'})}</span> <span class='content-type'>${Drupal.t("issues detected.",{},{context: 'problems'})}</span>`,
  panelControls: Drupal.t('Editorially'),
  buttonToolsContent: Drupal.t('Check headings & alt text'),
  buttonToolsActive: Drupal.t('Hide headings & alt text'),
  buttonOutlineContent: Drupal.t('Headings'),
  buttonAltsContent: Drupal.t('Alt Text'),
  buttonFirstContent: Drupal.t('Go to first alert'),
  buttonNextContent: Drupal.t('Go to next alert'),
  buttonPrevContent: Drupal.t('Go to previous alert'),
  buttonShowHiddenAlert: Drupal.t('Show hidden alert'),
  buttonHideHiddenAlert: Drupal.t('Hide hidden alert'),
  buttonShowHiddenAlerts: (count) => Drupal.t('Show') + ' ' + count + ' ' + Drupal.t('hidden alerts'),
  buttonHideHiddenAlerts: (count) => Drupal.t('Hide') + ' ' + count + ' ' + Drupal.t('hidden alerts'),
  buttonShowAlerts: Drupal.t('Show accessibility alerts'),
  buttonShowNoAlert: Drupal.t('Show accessibility checker'),
  buttonHideChecker: Drupal.t('Hide accessibility checker'),
  buttonHideAlerts: Drupal.t('Hide accessibility alerts'),
  panelCheckOutline: '<p>' + Drupal.t('This shows the heading outline. Check that it matches how the content is organized visually.') + '</p>',
  panelCheckAltText: '<p>' + Drupal.t('Check that each image describes what it means in context, and that there are no images of text.') + '</p>',

  noImagesFound: Drupal.t('No images found.'),
  altLabelPrefix: Drupal.t("Alt text: "),
  errorAltMissing: Drupal.t("(missing!)"),
  errorAltNull: Drupal.t("(none; image marked as decorative)"),
  errorOutlinePrefixSkippedLevel: Drupal.t("(flagged for skipped level) "),
  errorOutlinePrefixHeadingEmpty: Drupal.t("(empty heading) "),
  errorOutlinePrefixHeadingIsLong: Drupal.t("(flagged for length) "),

  // Errors and alerts
  consoleNotSupported: Drupal.t('This browser can not run Editoria11y.'),
  jumpedToInvisibleTip: Drupal.t("Note: this content may not be visible. Look for it inside the outlined container."),
  jumpedToAriaHiddenTip: Drupal.t("The item with this issue may be invisible or off screen.",{},{context: 'problems'}),

  // Strings used in tests
  suspiciousWords: [
    Drupal.t("image of"),
    Drupal.t("graphic of"),
    Drupal.t("picture of"),
    Drupal.t("placeholder"),
    Drupal.t("photo of"),
    Drupal.t('spacer'),
    Drupal.t('tbd'),
    Drupal.t('todo'),
    Drupal.t('copyright'),
    Drupal.t('courtesy of'),
    Drupal.t('photo by')
  ],
  meaninglessAlt: [
    Drupal.t('alt'),
    Drupal.t('chart'),
    Drupal.t('decorative'),
    Drupal.t('image'),
    Drupal.t('graphic'),
    Drupal.t('photo'),
    Drupal.t('placeholder'),
    Drupal.t('placeholder image'),
    Drupal.t('spacer'),
    Drupal.t('tbd'),
    Drupal.t('todo'),
    Drupal.t('to do'),
    Drupal.t('copyright'),
    Drupal.t('courtesy of'),
    Drupal.t('photo by')
  ],
  linksUrls: ['http:/', 'https:/', '.asp', '.htm', '.php', '.edu/', '.com/'],
  linkStringsNewWindows: new RegExp(`(${[
    Drupal.t('window',{},{context: 'Browser window'}),
    Drupal.t('tab',{},{context: 'Browser tab'}),
    Drupal.t('download'),
    'window','tab','download',
  ].join('|')})`, 'g'),
  linksMeaningless: new RegExp(`(
    ${[
      'learn','to','more','now','this','page','link','site','website','check','out','view','our','read','download','form','here','click',
      Drupal.t('learn'),
      Drupal.t('to'),
      Drupal.t('more'),
      Drupal.t('now'),
      Drupal.t('this'),
      Drupal.t('page'),
      Drupal.t('link'),
      Drupal.t('site'),
      Drupal.t('website'),
      Drupal.t('check'),
      Drupal.t('out'),
      Drupal.t('view'),
      Drupal.t('our'),
      Drupal.t('read'),
      Drupal.t('download'),
      Drupal.t('form'),
      Drupal.t('here'),
      Drupal.t('click'),
      Drupal.t('learn more'),
      Drupal.t('this page'),
      Drupal.t('this link'),
      Drupal.t('this site'),
      Drupal.t('our website'),
      Drupal.t('check out'),
      Drupal.t('view our'),
      Drupal.t('click here'),
      '\\.',"'",'"',":",'<','>','\\s','\\?','-',',',':'
    ].join('|')})+`, 'g'),

  // Tooltips base ======================================

  toggleManualCheck: Drupal.t("manual check needed"),
  toggleAlert: Drupal.t("alert"),
  issue: Drupal.t('Issue',{},{context: 'problems'}),
  toggleAriaLabel: (label) => Drupal.t("Accessibility %label", {
    '%label': label
  }),
  transferFocus: Drupal.t('Edit this content'),
  dismissOkButtonContent: Drupal.t('Mark as checked and OK'),
  dismissHideButtonContent: Drupal.t('Hide alert'),
  dismissActions: (count) =>  Drupal.t("@count similar issues", {
    '@count': count
  }), // 2.3.10
  dismissHideAllButton: Drupal.t('Ignore all like this'), // 2.3.10
  dismissOkAllButton: Drupal.t('Mark all like this as OK'), // 2.3.10
  dismissOkTitle: Drupal.t('Hides this alert for all editors'),
  dismissHideTitle: Drupal.t('Hides this alert for you'),
  undismissOKButton: Drupal.t('Restore this alert marked as OK'),
  undismissHideButton: Drupal.t('Restore this hidden alert'),
  undismissNotePermissions: Drupal.t('This alert has been hidden by an administrator'),
  reportsLink: Drupal.t('Open site reports in new tab'),
  closeTip: Drupal.t('Close'),
  panelHelpTitle: Drupal.t('About this tool'),
  panelHelp: `<p>
                <a href=\'@demo\' target=\'_blank\'>Editoria11y</a>
                 ${Drupal.t("@Editoria11yLink checks for common accessibility needs, such as image alternative text, meaningful heading outlines and well-named links.", {'@Editoria11yLink': ''})}
              </p>
              <p>
                ${Drupal.t('Many alerts are "manual checks." Manual checks can be dismissed:')}
              </p>
              <ul>
                <li>
                  ${Drupal.t('"Mark as checked and OK" hides the alert for all editors.')}</li>
                <li>
                  ${Drupal.t('"Ignore this manual check" leaves the tip visible to other editors.')}
                </li>
              </ul>
              <p>${Drupal.t('Dismissed alerts can be found via the "Show hidden alerts" toggle.')}
              </p>
              <p>${Drupal.t("If an incorrect alert is appearing on many pages, site administrators can tell the checker to ignore particular elements and page regions.")}
              </p>
              <p>
                ${Drupal.t("And remember that automated checkers cannot replace proofreading and testing for accessibility.")}
              </p>
              <p><br><a href='https://github.com/itmaybejj/editoria11y/issues' class='ed11y-small' target='_blank'>${Drupal.t("Report bugs & request changes")}</a>.
              </p>`,


  // Tooltips for heading tests =========================

  headingExample : `
    <ul>
        <li>${Drupal.t("Heading level 1")}
            <ul>
                <li>${Drupal.t("Heading level 2: a topic")}
                    <ul>
                        <li>${Drupal.t("Heading level 3: a subtopic")}</li>
                    </ul>
                </li>
                <li>${Drupal.t("Heading level 2: a new topic")}</li>
            </ul>
        </li>
    </ul>`
  ,

  // todo: import rewritten tips from library.
  headingLevelSkipped : {
    title: Drupal.t("Manual check: was a heading level skipped?"),
    tip: (prevLevel = '', level = '') =>
      `<p>
        ${Drupal.t("Headings and subheadings create a navigable table of contents for assistive devices. The numbers indicate indents in a nesting relationship:")}
      </p>
      ${Ed11y.M.headingExample}
      <p>${Drupal.t("This heading skipped from level %prevLevel to level %level. From a screen reader, this sounds like content is missing.", 
          {
            '%prevLevel': prevLevel,
            '%level': level
          })
        }
       <p>
        ${Drupal.t("<strong>To fix:</strong> adjust levels to form an accurate outline, without gaps.")}
      </p>
      `,
  },

  headingEmpty : {
    title: Drupal.t("Heading tag without any text"),
    tip: () => `
      <p>
      ${Drupal.t("Headings and subheadings create a navigable table of contents for assistive devices. The numbers indicate indents in a nesting relationship:")}
      </p>
      ${Ed11y.M.headingExample}
      <p>${Drupal.t("Empty headings create confusing gaps in this outline: they could mean the following content is still part of the previous section, or that the text was unpronounceable for some reason.")}</p>
      <p>
        ${Drupal.t("<strong>To fix:</strong> add text to this heading, or delete this empty line.")}
      </p>`,
  },

  headingIsLong : {
    title: Drupal.t("Manual check: long heading"),
    tip: () => `
       <p>
        ${Drupal.t("Headings and subheadings create a navigable table of contents for assistive devices. The numbers indicate indents in a nesting relationship:")}
      </p>
      ${Ed11y.M.headingExample}
      <p>
        ${Drupal.t("<strong>To fix:</strong> shorten this heading if possible, or remove the heading style if it was only applied to this text to provide visual emphasis.")}
      </p>
      `,
  },

  blockquoteIsShort : {
    title: Drupal.t("Manual check: is this a blockquote?"),
    tip: () => `
      <p>
        ${Drupal.t("Blockquote formatting tells screen readers that the text should be announced as a quotation. This was flagged because short blockquotes are sometimes actually headings. If this <em>is</em> a heading and not a quotation, use heading formatting instead, so this appears in the page outline.")}
      </p>
    `,
  },

  // Tooltips for image tests

  // Reusable example for tips:
  altAttributeExample : `
    <p>
      ${Drupal.t("Note that a good alt describes the image's message, not simply what it contains. Depending on the context, the alt for the picture of a child kicking a ball might emphasize the setting, the child, the kick or the ball:")};
    </p>
      <ul>
          <li>${Drupal.t("Child happily kicking a ball on a summer day")}</li>
          <li>${Drupal.t("A.J. playing in the new team uniform")}</li>
          <li>${Drupal.t("A.J.'s game-winning kick curved in from the left sideline!")}</li>
          <li>${Drupal.t("The medium ball is the right size for this 9-year-old child")}</li>
      </ul>
  `,

  altMissing : {
    title: Drupal.t("Image has no alternative text attribute"),
    tip: () => `
      <p>${Drupal.t("When screen readers encounter an image with no alt attribute at all, they dictate the url of the image file instead, often one letter at a time.")}</p>
      <p>${Drupal.t('To fix: either add an empty alt (alt="") to indicate this image should be ignored by screen readers, or add descriptive alt text.')}</p>
      ${Ed11y.M.altAttributeExample}
    `
  },

  altNull : {
    title: Drupal.t("Manual check: image has no alt text"),
    tip: () => '<p>' + Drupal.t("" +
      "Unless this image is purely decorative (a spacer icon or background texture), an alt should probably be provided. Photos in page content <strong>almost always need alt text.</strong> Since many screen reader users can see there is an image present, it can be very confusing to move the cursor across the place on the page where an image is visible, but hear nothing.") + "</p>" +
       Ed11y.M.altAttributeExample,
  },

  altURL : {
    title: Drupal.t("Image's text alternative is a URL"),
    tip: (alt = '') => `
      <p>${Drupal.t('The alt text for this image is "%alt," which probably describes the file name, not the contents of the image.', {'%alt': alt})}</p>
      <p>${Drupal.t("<strong>To fix:</strong> set this image's alternative text to a concise description of what this image means in this context.")}</p>
      ${Ed11y.M.altAttributeExample}`
    ,
  },

  altMeaningless : {
    title: Drupal.t('Alt text is meaningless'),
    tip: (alt ='') => `
      <p>${Drupal.t('The alt text for this image is "%alt," which was flagged for being common placeholder text.', {
          '%alt': alt,
      })}</p>
    <p>${Drupal.t("<strong>To fix:</strong> set this image's alternative text to a concise description of what this image means in this context.")}</p>
    ${Ed11y.M.altAttributeExample}    
    `,
  },
  altMeaninglessLinked : {
    title: 'Linked alt text is meaningless',
    tip: (alt = '') =>`
        <p>
          ${Drupal.t("When a link includes an image, the image's alt text becomes the link text announced by screen readers. Links should clearly and concisely describe their destination, even out of context.")}
        </p>
        <p>${Drupal.t('The alt text for this image is "%alt," which probably does not describe this link.', {
          '%alt': alt,
        })}</p>
        `,
  },

  altURLLinked : {
    title: Drupal.t("Linked image's text alternative is a URL"),
    tip: (alt = '') => `
        <p>${Drupal.t('The alt text for this image is "%alt," which probably does not describe this link.', {
        '%alt': alt,
      })}</p>
        <p>
          ${Drupal.t("When a link is wrapped around an image and there is no other text, the image's alt text becomes the link text announced by screen readers. Links should clearly and concisely describe their destination; a URL (usually pronounced by the screen reader one letter at a time) does not.")}
        </p>
        <ul>
          <li>${Drupal.t('Good link text: "About us"')}</li>
          <li>${Drupal.t('Bad link text: "H T T P S colon forward slash forward slash example dot com forward slash aye bee oh you tee you ess"')}</li>
        </ul>
        `,
  },

  altImageOf : {
    title: 'Manual check: possibly redundant text in alt',
    tip: (alt = '') => `
      <p>${Drupal.t('The alt text for this image is "%alt," which mentions that this image is an image.', {
        '%alt': alt,
      })}</p>
      <p>${Drupal.t('Screen readers announce they are describing an image when reading alt text, so phrases like "image of" and "photo of" are usually redundant in alt text; the screen reader user hears "image: image of something."')}</p>
      <p>${Drupal.t("Note that this is OK if the format is referring to the <strong>content</strong> of the image:")}</p>
      <ul>
        <li>${Drupal.t('Format is redundant: "<em>photo of</em> a VHS tape"')}</li>
        <li>${Drupal.t('Format is relevant: "<em>photo of</em> a VHS tape in a photo album being discussed in a history class"', {'%alt': alt,})}</li>
      </ul>
    `,
  },

  altImageOfLinked : {
    title: Drupal.t("Manual check: possibly redundant text in linked image"),
    tip: (alt = '') => `
      <p>${Drupal.t('The alt text for this image is "%alt," which mentions that this image is an image."', {
        '%alt': alt,
      })}</p>
        <p>${Drupal.t('Links should clearly and concisely describe their destination. Since words like "image," "graphic" or "photo" are already redundant in text alternatives (screen readers already identify the image as an image), their presence in a linked image usually means the text alternative is describing the image instead of the link')}</p>
        <p>${Drupal.t("Note that this is OK if the format is referring to the <strong>content</strong> of the image:")}</p>
        <ul>
          <li>${Drupal.t('Good link text: "About us"')}</li>
          <li>${Drupal.t('Bad link text: "Image of five people jumping"')}</li>
        </ul>
    `,
  },

  altDeadspace : {
    title: Drupal.t( "Image's text alternative is unpronounceable"),
    tip: (alt = '') => `
      <p>
        ${Drupal.t('The alt text for this image is "%alt," which only contains unpronounceable symbols and/or spaces. Screen readers will announce that an image is present, and then pause awkwardly: "image: ____."', {
          '%alt': alt,
        })}
        </p>
        <p>
          ${Drupal.t('Links should clearly and concisely describe their destination. Since words like "image," "graphic" or "photo" are already redundant in text alternatives (screen readers already identify the image as an image), their presence in a linked image usually means the text alternative is describing the image instead of the link')}</p>
        <p>
          ${Drupal.t('<strong>To fix:</strong> add a descriptive alt, or provide a <em>completely</em> empty alt (alt="") if this is just an icon or spacer, and screen readers should ignore it.')}
        </p>
        ${Ed11y.M.altAttributeExample}`,
  },

  altEmptyLinked : {
    title: Drupal.t("Linked Image has no alt text"),
    tip: () => `
      <p>${Drupal.t("When a link is wrapped around an image, the image's alt text provides the link's title for screen readers.")}</p>
      <p>${Drupal.t("<strong>To fix:</strong> set this image's alternative text to something that describes the link's destination, or add text next to the image, within the link.")}
      </p>
    `,
  },

  altLong : {
    title: Drupal.t("Manual check: very long alternative text"),
    tip: (alt = '') => `
      <p>
        ${Drupal.t("Image text alternatives are announced by screen readers as a single run-on sentence; listeners must listen to the entire alt a second time if they miss something. If this cannot be reworded to something succinct, it is better to use the alt to reference a visible text alternative for complex images. For example:")}
      </p>
      <ul>
        <li>${Drupal.t('"Event poster; details follow in caption"')}</li>
        <li>${Drupal.t('"Chart showing our issues going to zero; details follow in table"')}</li>
      </ul>
      <p>${Drupal.t("This image's alt text is: %alt",{'%alt': alt})}</p>
    `,
  },

  altLongLinked : {
    title: Drupal.t("Manual check: very long alternative text in linked image"),
    tip: (alt = '') => `
      <p>
        ${Drupal.t("The alt text on a linked image is used to describe the link destination. Links should be brief, clear and concise, as screen reader users often listen to the list of links on the page to find content of interest. Long alternative text inside a link often indicates that the image's text alternative is describing the image instead rather than the link.")}
      </p>
      <p>
        ${Drupal.t("This image's alt text is: <em>%alt</em>", {
          '%alt': alt,
        })}
      </p>`
    ,
  },

  altPartOfLinkWithText : {
    title: Drupal.t("Manual check: link contains both text and an image"),
    tip: (alt = '') => `
        <p>${Drupal.t("Screen readers will include the image's alt text when describing this link")}</p>
        <p>${Drupal.t("Check that the combined text is concise and meaningful:")}
          <br>"<em><strong>${alt}</strong></em>"
        </p>
        <ul>
          <li>${Drupal.t("Keep alts that add relevant meaning:")}<br>
            ${Drupal.t('"Buy (A Tigers v. Falcons ticket)."')}</li>
          <li>${Drupal.t("Edit unhelpful or irrelevant alts:")}<br>
            ${Drupal.t('"Buy (A piece of paper with team logos on it)."')}</li>
            <li>${Drupal.t("Remove unnecessary alts:")}<br>
            ${Drupal.t('"Buy Tigers v. Falcons tickets (A Tigers v. Falcons ticket)."')}</li>
        </ul>
      `, // 2.3.10.
  },

    // todo check br in translation
  linkNoTextExample: '<p>' + Drupal.t('Screen readers will either say nothing when they reach this link: <br><em>"Link, [...awkward pause where the link title should be...],"</em><br>or read the URL: <br><em>"Link, H-T-T-P-S forward-slash forward-slash example dot com"</em>') + '</p>',

  linkTextIgnored: (ignoredText) => '<p>' + Drupal.t('Screen readers will only read the text of the link type indicator on this link:<br><em>"<strong>%text</strong>"</em>', {
    '%text': ignoredText,
    }
  ) + '</p>',

  linkNoText : {
    title: Drupal.t("Link with no accessible text"),
    tip: (ignoredText = '') => `
    <p>${Drupal.t("This link is either a typo (a linked space character), or a linked image with no text alternative.")}</p>
    ${ignoredText ? Ed11y.M.linkTextIgnored(ignoredText) : Ed11y.M.linkNoTextExample}
    <p><strong>${Drupal.t("To fix: ")}</strong></p>  
    <ul>
      <li>${Drupal.t('If this a typo, delete it. Note that typo links can be hard to see if they are next to a "real" link: one will be on the text, one on a space.')}</li>
      <li>${Drupal.t("If it is a real link, add text to describe where it goes.")}</li>
    </ul>`
  },

  linkText: (text) =>
    "<p>" + Drupal.t("This link's text is:") + " <strong>" + text + "</strong></p>",

  linkTextIsURL : {
    title: Drupal.t("Manual check: is this link text a URL?"),
    tip: (text = '') => Ed11y.M.linkText(text) +
      `<p>
        ${Drupal.t("Links should be meaningful and concise. Readers often skim by link titles. This is especially true of screen reader users, who navigate using a list of on-page links. A linked URL breaks this pattern; the reader has to read the preceding paragraph to figure out the link's purpose from context.")}
      </p>
      <ul>
        <li>${Drupal.t('Meaningful and concise link: "Tips for writing meaningful links"')}</li>
        <li>${Drupal.t('Linked URL, as pronounced by a screen reader: "H T T P S colon forward-slash forward-slash example dot com forward-slash tips forward-slash meaningful-links"')}</li>
</ul>`,
  },

  linkTextIsGeneric : { // todo: test inline URLs and overriding
    title: Drupal.t("Manual check: is this link meaningful and concise?"),
    tip: (text = '') => Ed11y.M.linkText(text) +
      `<p>${Drupal.t("Readers skim for links. This is especially true of screen reader users, who navigate using a list of on-page links.")}</p>
       <p>${Drupal.t('Generic links like "click here", "read more" or "download" expect the reader be reading slowly and carefully, such that they figure out the purpose of each link from context for themselves. Few readers do this, so click-through rates on meaningless links are extremely poor.')}</p>
       <ul>
        <li>${Drupal.t('Not meaningful: "Click here to learn about meaningful links".')}</li>
        <li>${Drupal.t('Not concise: "Click here to learn about meaningful links"')}</li>
        <li>${Drupal.t('Ideal: "Learn about meaningful links"')}</li>
       </ul>
      `
  },

  linkDocument : {
    title : Drupal.t( "Manual check: is the linked document accessible?"),
    tip: () => `
      <p>${Drupal.t("Many mobile and assistive device users struggle to read content in PDFs. PDFs generally do not allow for changing font sizes, and often contain features that are incompatible with screen readers.")}</p>
      <p>${Drupal.t('Ideally make the content of this linked PDF available on a Web page or in an editable document, and only link to this PDF as a "printable" alternative. If this PDF is the only way you are providing to access this content, you will need to manually check that the PDF is well-structured, with headings, lists and table headers, and provides alt text for its images.', {
        '@url': 'https://webaim.org/techniques/acrobat/',
      })}</p>
    `,
  },

  linkNewWindow : {
    title: Drupal.t("Manual check: is opening a new window expected?"),
    tip: () => `
      <p>${Drupal.t("Readers can always choose to open a link a new window. When a link forces open a new window, it can be confusing and annoying, especially for assistive device users who may wonder why their browser's back button is suddenly disabled.")}</p>
      <p>${Drupal.t("There are two general exceptions:")}</p>
      <ul>
        <li>${Drupal.t("When the user is filling out a form, and opening a link in the same window would cause them to lose their work.")}</li>
        <li>${Drupal.t("When the user is clearly warned a link will open a new window.")}</li>
      </ul>
      <p>${Drupal.t("To fix: set this link back its default target, or add a screen-reader accessible warning (text or an icon with alt text).")}</p>
    `,
  },

  // Tooltips for Text QA

  tableNoHeaderCells : {
    title: Drupal.t("Table has no header cells"),
    tip: () => `
      <p>${Drupal.t("To fix:")}</p>
      <ul>
        <li>${Drupal.t("If this table contains data that is meaningfully organized by row and column, edit the table's properties and specify whether headers have been placed in the first row, column or both. This lets screen reader users hear the headers repeated while navigating the content.")}</li>
        <li>${Drupal.t("If this table does not contain rows and columns of data, but is instead being used for visual layout, remove it. Tables overflow the page rather than reflowing on mobile devices, and should only be used when the horizontal relationships are necessary to understand the content.")}</li>
      </ul>
    `,
  },

  tableContainsContentHeading : {
    title: Drupal.t("Content heading inside a table"),
    tip: () => `
      <p>${Drupal.t("To fix: remove heading formatting. Use row and column headers instead.")}</p>
      <p>${Drupal.t('Content headings ("Heading 1", "Heading 2") form a navigable table of contents for screen reader users, labelling all content <strong>until the next heading</strong>. Table headers label specific columns or rows within a table.')}</p>
      <table><tr><th>1</th><th>2</th><th>3</th><td rowspan="2">${Drupal.t("To illustrate: a <strong>table</strong> header in cell 2 would only label its column: cell B.")}<br><br>
            ${Drupal.t("A <strong>content</strong> heading in cell 2 would label all subsequent text, reading from left to right: cells 3, A, B and C, as well as this text!")}</td></tr>
            <tr><td>A</td><td>B</td><td>C</td></table>
      <ul>`
    ,
  },

  tableEmptyHeaderCell : {
    title: Drupal.t("Empty table header cell"),
    tip: () => `
        <p>${Drupal.t("When exploring tables, screen readers repeat table header cells as needed to orient users. Without headers, it is very easy to get lost; screen reader users have to count columns and rows and try to remember which columns went with which rows.")}</p>
        <p>${Drupal.t("<strong>To fix:</strong> make sure each header cell in this table contains text.")}</p>
    `
  },

  textPossibleList : {
    title: Drupal.t("Manual check: should this have list formatting?"),
    tip : (text = '') => `
      <p>${Drupal.t("List formatting is structural:")}</p>
      <ol>
        <li>${Drupal.t('List formatting indents and reflows on overflow. Text aligns vertically with text, rather than the "%text"', {
          '%text': text
        })}</li>
        <li>${Drupal.t('Lists are machine-readable. Screen readers can orient their users, announcing this as "list item, 2 of 2."')}</li>
      </ol>
      <p>${Drupal.t("3. Whereas this unformatted item (just a number, typed as text) is not visually or audibly included in the list.")}</p>
      <p>${Drupal.t('To fix: if this "%text" starts a list, replace it with list formatting.',{
        '%text': text
      })}</p>
    `
  },

  textPossibleHeading : {
    title: Drupal.t("Manual check: should this be a heading?"),
    tip : () => `
      <p>${Drupal.t("If this all-bold line of text is functioning as a heading for the following text rather than a visual emphasis, replace the bold formatting with the appropriately numbered heading. Otherwise, dismiss this alert.")}</p>
      <p>${Drupal.t("Headings and subheadings create a navigable table of contents for assistive devices. The heading's number indicates its depth in the page outline; e.g.:")}</p>
      ${Ed11y.M.headingExample}`,
  },

  textUppercase : {
    title: Drupal.t("Manual check: is this uppercase text needed?"),
    tip : () => `<p>${Drupal.t('UPPERCASE TEXT CAN BE MORE DIFFICULT TO READ FOR MANY PEOPLE, AND IS OFTEN INTERPRETED AS SHOUTING.')}</p>
      <p>${Drupal.t('Consider using sentence case instead, and using bold text or font changes for visual emphasis, or structural formatting like headings for emphasis that will also be announced by screen readers.')}</p>`
  },

  embedVideo : {
    title: Drupal.t("Manual check: is this video accurately captioned?"),
    tip : () => `<p>${Drupal.t('If a recorded video contains speech or meaningful sounds, it must provide captions')}</p>
      <p>${Drupal.t('Note that automatic, machine-generated captions must be proofread, and speaker identifications must be added, before being considered an equal alternative')}</p>`,
  },
  embedAudio : {
    title: Drupal.t("Manual check: is an accurate transcript provided?"),
    tip : () => `
       <p>${Drupal.t("If this audio contains speech, a text alternative must be provided on this page or linked.", {
          '@url': 'https://www.w3.org/WAI/media/av/transcribing/',
        })}</p>
      <p>${Drupal.t("Note that automatic, machine-generated transcripts must be proofread, and speaker identifications must be added, before being considered an equal alternative")}</p>
    `,
  },
  embedVisualization : {
    title: Drupal.t("Manual check: is this visualization accessible?"),
    tip : () => `
        <p>${Drupal.t("Visualization widgets are often difficult or impossible for assistive devices to operate, and can be difficult to understand for readers with low vision or colorblindness.")}</p>
        <p>${Drupal.t("Unless this particular widget has high visual contrast, can be operated by a keyboard and described by a screen reader, assume that an alternate format (text description, data table or downloadable spreadsheet) should also be provided.")}</p>`,
  },
  embedTwitter : {
    title: Drupal.t("Manual check: is this embed a keyboard trap?"),
    tip : () => `
      <p>${Drupal.t("If embedded feeds are set to show a high number of items, keyboard users may have to click the tab key dozens or hundreds of times to exit the component.")}</p>
      <p>${Drupal.t('Check to make sure only a small number of items auto-load immediately or while scrolling. Having additional items load on request ("show more") is fine.')}</p>
      `,
  },
  embedCustom : {
    title: Drupal.t("Manual check: is this embedded content accessible?"),
    tip : () => `<p>${Drupal.t("Please make sure images inside this embed have alt text, videos have captions, and interactive components can be operated by a keyboard.")}</p>`,
  }

};

