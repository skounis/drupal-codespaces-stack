describe('Authentication tweaks', () => {

  beforeEach(() => {
    cy.setUp('standard').applyRecipe();
  });

  after(() => {
    cy.tearDown();
  });

  it('hides the password field if user will be notified of new account', () => {
    cy.drupalLogin('admin');
    cy.visit('/admin/people/create');

    const notifyCheckbox = cy.findByLabelText('Notify user of new account')
      .should('be.checked');
    const passwordField = cy.findByLabelText('Password')
      .should('exist')
      .and('not.be.visible');
    notifyCheckbox.uncheck();
    passwordField.should('be.visible').and('be.empty');
    cy.findByLabelText('Confirm password')
      .should('exist')
      .and('be.empty')
      .and('not.be.visible');
    notifyCheckbox.check();

    cy.findByLabelText('Email address').type('chef@drupal.local');
    cy.findByLabelText('Username').type('chef');
    cy.findByDisplayValue('Create new account').click();
    cy.get('[data-drupal-messages]').should('contain.text', 'A welcome message with further instructions has been emailed to the new user chef.');
  });

})
