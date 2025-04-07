describe('Contact form', () => {

  beforeEach(() => {
    cy.setUp('standard').applyRecipe();
  });

  after(() => {
    cy.tearDown();
  });

  it('requires all fields and has spam protection', () => {
    // Ensure the viewport is wide enough to see the main menu links.
    cy.viewport('macbook-16');
    // Use the main menu link to visit the contact form.
    cy.visit('/');
    cy.findByText('Main navigation')
        .parent()
        .find('a:contains("Contact")')
        .click();

    cy.get('[id^="webform-submission-contact-form-node-"]').within(() => {
      cy.findByLabelText('Name').should('have.attr', 'required');
      cy.findByLabelText('Email').should('have.attr', 'required');
      cy.findByLabelText('Message').should('have.attr', 'required');
      cy.findByText('CAPTCHA', { selector: 'fieldset > legend' })
          .should('be.visible')
          .parent()
          .findByText('Click to start verification')
          .should('exist');

      cy.get('input[name="url"]').should('not.be.visible');
    });
  });

});
