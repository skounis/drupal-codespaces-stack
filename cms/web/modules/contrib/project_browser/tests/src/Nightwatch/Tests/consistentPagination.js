module.exports = {
  '@tags': ['project_browser'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('project_browser_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test pagination consistency across tabs': function (browser) {
    browser.drupalLoginAsAdmin(() => {
      browser
        .drupalRelativeURL('/admin/modules/browse/project_browser_test_mock')
        .waitForElementVisible('h1', 100)
        .assert.textContains('h1', 'Browse projects')
        .click('input[name="security_advisory_coverage"]')
        .click('input[name="maintenance_status"]')
        .assert.visible('select.pagination__num-projects')
        .click('select.pagination__num-projects option[value="24"]');

      browser
        .openNewWindow('tab')
        .drupalRelativeURL('/admin/modules/browse/project_browser_test_mock')
        .waitForElementVisible('h1', 100)
        .assert.textContains('h1', 'Browse projects')
        .click('input[name="security_advisory_coverage"]')
        .click('input[name="maintenance_status"]')
        .assert.visible('select.pagination__num-projects')
        .getValue('select.pagination__num-projects', function (result) {
          this.assert.strictEqual(
            result.value,
            '12',
            'The page size is reset in the second tab.',
          );
        });
    });
  },
};
