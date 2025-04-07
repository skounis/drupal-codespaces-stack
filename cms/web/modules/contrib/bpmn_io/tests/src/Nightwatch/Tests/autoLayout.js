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
  'Use auto-layout on an existing model': (browser) => {
    const now = Date.now();
    const screenshotsPath = `${browser.screenshotsPath}/Tests/bpmn_io/${now}/auto_layout`;

    browser
      .drupalLoginAsAdmin(() => {
        browser
          .resizeWindow(3840, 2160)
          .drupalRelativeURL('/admin/config/workflow/eca')
          .waitForElementVisible('body', 1000)
          .click('ul[data-drupal-selector="edit-eca-entities-eca-bpmn-io-operations-data"] > li.edit > a')
          .assert.elementPresent('[data-element-id="Event_0erz1e4"]')
          .assert.elementPresent('[data-element-id="Gateway_1rthid4"]')
          .assert.elementPresent('[data-element-id="Flow_0a1zeo8"]')
          .assert.elementPresent('[data-element-id="Activity_1vtj47i"]')
          .saveScreenshot(`${screenshotsPath}/00_original.png`)
          .click('[data-drupal-selector="edit-layout-process"]')
          .saveScreenshot(`${screenshotsPath}/01_result.png`)
          .assert.elementPresent('[data-element-id="Event_0erz1e4"]')
          .assert.elementPresent('[data-element-id="Gateway_1rthid4"]')
          .assert.elementPresent('[data-element-id="Flow_0a1zeo8"]')
          .assert.elementPresent('[data-element-id="Activity_1vtj47i"]')
      });
  },
}
