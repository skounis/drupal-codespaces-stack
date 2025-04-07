const path = require('path');

module.exports = {
  '@tags': ['bpmn_io'],
  before: function (browser) {
    browser.drupalInstall({
      installProfile: 'nightwatch_a11y_testing',
      setupFile: path.normalize(`${__dirname}/../../TestSite/TestSiteInstallTestScript.php`),
    });
  },
  after: function (browser) {
    browser.drupalUninstall();
  },
  'Convert the ECA model': (browser) => {
    const now = Date.now();
    const screenshotsPath = `${browser.screenshotsPath}/Tests/bpmn_io/${now}/convert`;

    browser
      .drupalLoginAsAdmin(() => {
        browser
          .resizeWindow(3840, 2160)
          .drupalRelativeURL('/admin/config/workflow/eca')
          .waitForElementVisible('body', 1000)
          .assert.textContains('h1', 'Configure ECA - Events, Conditions, Actions')
          .saveScreenshot(`${screenshotsPath}/00_overview.png`)
          .click('ul[data-drupal-selector="edit-eca-entities-eca-fallback-operations-data"] > li.bpmn-io-convert > a')
          .assert.textContains('h1', 'Edit with BPMN.io')
          .saveScreenshot(`${screenshotsPath}/010_convert.png`)
          .assert.urlContains('/admin/config/workflow/eca/eca_fallback/edit')
          .assert.textContains('h1', 'ECA Feature Demo - Fallback ECA Model')
          .saveScreenshot(`${screenshotsPath}/011_convert.png`)
          .assert.elementPresent('[data-element-id="Event_0erz1e4"]')
          .assert.elementPresent('[data-element-id="Gateway_1rthid4"]')
          .assert.elementPresent('[data-element-id="Flow_0c7hrjx"]')
          .assert.elementPresent('[data-element-id="Activity_1vtj47i"]');
      })
      .end();
  },
};
