import chaiString from 'chai-string';

chai.use(chaiString);

describe('Basic SEO', () => {

  beforeEach(() => {
    cy.setUp('standard').applyRecipe();
  });

  after(() => {
    cy.tearDown();
  });

  it('generates URL aliases based on menu paths', () => {
    const createPage = (title, parentLink, expectedAlias) => {
      cy.visit('/node/add/page');
      cy.findByLabelText('Title').type(title);
      cy.findByText('Menu settings', {
        selector: 'details:not(open) > summary',
        exact: false,
      }).click();
      cy.findByLabelText('Provide a menu link').check();
      cy.findByLabelText('Menu link title').should('contain.value', title);
      cy.findByLabelText('Parent link').select(parentLink);
      cy.findByDisplayValue('Save').click();

      cy.location('pathname').should('endWith', expectedAlias);
    };

    cy.drupalLogin('admin');
    // Create a new page with a link at the top level of the main menu.
    createPage('Parent', '<Main navigation>', '/parent');
    // Create another page under Parent and confirm that its URL alias reflects
    // the menu hierarchy.
    createPage('Child', '-- Parent', '/parent/child');
    cy.drupalLogout();
  });

  it('allows content editors to manage redirects', () => {
    cy.drupalLogin('admin')
      .drupalCreateUser('editor', ['content_editor'])
      .drupalLogout()
      .drupalLogin('editor');

    cy.visit('/admin/config/search/redirect');
    cy.get('.page-title').should('contain.text', 'URL redirects');
    cy.findByText('Add redirect', { selector: 'a' }).should('exist');
  });

})
