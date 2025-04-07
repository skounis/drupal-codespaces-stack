const yoast = require('yoastseo');

const defaultsDeep  = require( "lodash/defaultsDeep" );
const isObject      = require( "lodash/isObject" );
const isString      = require( "lodash/isString" );
const isUndefined   = require( "lodash/isUndefined" );
const isEmpty       = require( "lodash/isEmpty" );
const forEach       = require( "lodash/forEach" );

const ContentAssessor = yoast.ContentAssessor;
const Paper           = yoast.Paper;
const Pluggable       = yoast.Pluggable;
const Researcher      = yoast.Researcher;
const SEOAssessor     = yoast.SEOAssessor;

// Import the following 'private' classes
const CornerstoneSEOAssessor      = require( "yoastseo/js/cornerstone/seoAssessor.js" );
const CornerstoneContentAssessor  = require( "yoastseo/js/cornerstone/contentAssessor.js" );
const AssessorPresenter           = require( "yoastseo/js/renderers/AssessorPresenter.js" );

const removeHtmlBlocks = require( "yoastseo/js/stringProcessing/htmlParser.js" );

/**
 * Default config for Real Time SEO
 *
 * @type {Object}
 */
const defaults = {
  // targets.output should be defined as well but not by default
  callbacks: {
    saveScores: function() {},
    saveContentScore: function() {},
  },
  locale: "en_US",
  keywordAnalysisActive: true,
  contentAnalysisActive: true,
};

/**
 * Check arguments passed to the App to check if all necessary arguments are set.
 *
 * @private
 * @param {Object}      args            The arguments object passed to the App.
 * @returns {void}
 */
function verifyArguments( args ) {
  if ( ! isObject( args.callbacks.getData ) ) {
    throw new MissingArgument( "The app requires an object with a getData callback." );
  }

  if ( ! isObject( args.targets ) ) {
    throw new MissingArgument( "`targets` is a required App argument, `targets` is not an object." );
  }
}

// TODO: Add TitleWidth as required data for getData callback or implement calculator

/**
 * This should return an object with the given properties
 *
 * @callback YoastSEO.App~getData
 * @returns {Object} data
 * @returns {String} data.keyword The keyword that should be used
 * @returns {String} data.meta
 * @returns {String} data.text The text to analyze
 * @returns {String} data.metaTitle The text in the HTML title tag
 * @returns {String} data.title The title to analyze
 * @returns {String} data.url The URL for the given page
 * @returns {String} data.excerpt Excerpt for the pages
 */

/**
 * @callback RealTimeSEO.App~saveScores
 *
 * @param {int} score The overall keyword score as determined by the assessor.
 * @param {AssessorPresenter} assessorPresenter The assessor presenter that will be used to render the keyword score.
 */

/**
 * @callback RealTimeSEO.App~saveContentScore
 *
 * @param {int} score The overall content score as determined by the assessor.
 * @param {AssessorPresenter} assessorPresenter The assessor presenter that will be used to render the content score.
 */

/**
 * Loader for the analyzer using YoastSEO.js
 *
 * As opposed to YoastSEO's default App, this App does not do any communicating
 * with the DOM but serves purely as a gateway to the libraries analyzer
 * functions.
 *
 * DOM manipulation should be handled by the implementer's core JavaScript (e.g.
 * Drupal behaviours for the Real-Time SEO module).
 *
 * @param {Object} args The arguments passed to the loader.
 * @param {String} args.targets.output ID for the element to put the output of the analyzer in.
 * @param {Object} args.callbacks The callbacks that the app requires.
 * @param {Object} args.assessor The Assessor to use instead of the default assessor.
 * @param {RealTimeSEO.App~getData} args.callbacks.getData Called to retrieve input data
 * @param {RealTimeSEO.App~saveScores} args.callbacks.saveScores Called when the score has been determined by the analyzer.
 * @param {RealTimeSEO.App~saveContentScore} args.callback.saveContentScore Called when the content score has been
 *                                                                       determined by the assessor.
 * @param {Function} args.marker The marker to use to apply the list of marks retrieved from an assessment.
 *
 *
 * @constructor
 */
const App = function ( args ) {
  if ( ! isObject( args ) ) {
    args = {};
  }

  defaultsDeep( args, defaults );

  verifyArguments( args );

  this.config = args;

  this.callbacks = this.config.callbacks;

  this.i18n = this.constructI18n();

  this.initializeAssessors( args );

  this.pluggable = new Pluggable( this );

  this.initAssessorPresenters();
};

/**
 * Creates a fake i18n object because it's required throughout YoastSEO.js
 *
 * @returns {Object}
 */
App.prototype.constructI18n = function( ) {
  // TODO: Figure out the required i18n functions and link them to Drupal.

  // Use the code below to see how the i18n is utilised and what its interface should be.
  function createHandler(message) {
    return function (target, thisArg, argumentsList) {
      // console.log(message, argumentsList);
    };
  }

  const handler = {
    apply: createHandler("i18n object used as function"),
    get: function (target, thisArg, voidArgs) {
      return new Proxy(function() {}, {
        apply: function (p, thatArg, argumentsList) {
          // console.log("i18n." + thisArg + " called", argumentsList);

          if (["dgettext", "dngettext"].indexOf(thisArg) !== -1) {
            return argumentsList[1];
          }
        }
      });
    }
  };

  return new Proxy({}, handler);
};

/**
 * Retrieves data from the callbacks.getData and applies modification to store these in this.rawData.
 *
 * @returns {void}
 */
App.prototype.getData = function() {
  this.rawData = this.callbacks.getData();

  if ( this.pluggable.loaded ) {
    this.rawData.metaTitle = this.pluggable._applyModifications( "data_page_title", this.rawData.metaTitle );
    this.rawData.meta = this.pluggable._applyModifications( "data_meta_desc", this.rawData.meta );
  }

  this.rawData.locale = this.config.locale;
};

/**
 * Initializes assessors based on if the respective analysis is active.
 *
 * @param {Object} args The arguments passed to the App.
 * @returns {void}
 */
App.prototype.initializeAssessors = function( args ) {
  this.initializeSEOAssessor( args );
  this.initializeContentAssessor( args );
};

/**
 * Initializes the SEO assessor.
 *
 * @param {Object} args The arguments passed to the App.
 * @returns {void}
 */
App.prototype.initializeSEOAssessor = function( args ) {
  if ( ! args.keywordAnalysisActive ) {
    return;
  }

  this.defaultSeoAssessor = new SEOAssessor( this.i18n, { marker: this.config.marker } );
  this.cornerStoneSeoAssessor = new CornerstoneSEOAssessor( this.i18n, { marker: this.config.marker } );

  // Set the assessor
  if ( isUndefined( args.seoAssessor ) ) {
    this.seoAssessor = this.defaultSeoAssessor;
  } else {
    this.seoAssessor = args.seoAssessor;
  }
};

/**
 * Initializes the content assessor.
 *
 * @param {Object} args The arguments passed to the App.
 * @returns {void}
 */
App.prototype.initializeContentAssessor = function( args ) {
  if ( ! args.contentAnalysisActive ) {
    return;
  }

  this.defaultContentAssessor = new ContentAssessor( this.i18n, { marker: this.config.marker, locale: this.config.locale }  );
  this.cornerStoneContentAssessor = new CornerstoneContentAssessor( this.i18n, { marker: this.config.marker, locale: this.config.locale } );

  // Set the content assessor
  if ( isUndefined( args.contentAssessor ) ) {
    this.contentAssessor = this.defaultContentAssessor;
  } else {
    this.contentAssessor = args.contentAssessor;
  }
};

/**
 * Initializes the assessorpresenters for content and SEO.
 *
 * @returns {void}
 */
App.prototype.initAssessorPresenters = function() {
  // Pass the assessor result through to the formatter
  if ( ! isUndefined( this.config.targets.output ) ) {
    this.seoAssessorPresenter = new AssessorPresenter( {
      targets: {
        output: this.config.targets.output,
      },
      assessor: this.seoAssessor,
      i18n: this.i18n,
    } );
  }

  if ( ! isUndefined( this.config.targets.contentOutput ) ) {
    // Pass the assessor result through to the formatter
    this.contentAssessorPresenter = new AssessorPresenter( {
      targets: {
        output: this.config.targets.contentOutput,
      },
      assessor: this.contentAssessor,
      i18n: this.i18n,
    } );
  }
};

/**
 * Inits a new pageAnalyzer with the inputs from the getInput function and calls the scoreFormatter
 * to format outputs.
 *
 * @returns {void}
 */
App.prototype.runAnalyzer = function() {
  if ( this.pluggable.loaded === false ) {
    return;
  }

  this.analyzerData = this.modifyData( this.rawData );

  let text = this.analyzerData.text;

  // Insert HTML stripping code
  text = removeHtmlBlocks( text );

  // Create a paper object for the Researcher
  this.paper = new Paper( text, {
    keyword: this.analyzerData.keyword,
    description: this.analyzerData.meta,
    url: this.analyzerData.url,
    title: this.analyzerData.metaTitle,
    titleWidth: this.analyzerData.titleWidth,
    locale: this.config.locale,
    permalink: this.analyzerData.permalink,
  } );

  // The new researcher
  if ( isUndefined( this.researcher ) ) {
    this.researcher = new Researcher( this.paper );
  } else {
    this.researcher.setPaper( this.paper );
  }

  if ( this.config.keywordAnalysisActive && ! isUndefined( this.seoAssessorPresenter ) ) {
    this.seoAssessor.assess( this.paper );

    this.seoAssessorPresenter.setKeyword( this.paper.getKeyword() );
    this.seoAssessorPresenter.render();

    this.callbacks.saveScores( this.seoAssessor.calculateOverallScore(), this.seoAssessorPresenter );
  }

  if ( this.config.contentAnalysisActive && ! isUndefined( this.contentAssessorPresenter ) ) {
    this.contentAssessor.assess( this.paper );

    this.contentAssessorPresenter.renderIndividualRatings();
    this.callbacks.saveContentScore( this.contentAssessor.calculateOverallScore(), this.contentAssessorPresenter );
  }
};

/**
 * Modifies the data with plugins before it is sent to the analyzer.
 *
 * @param   {Object}  data      The data to be modified.
 * @returns {Object}            The data with the applied modifications.
 */
App.prototype.modifyData = function( data ) {
  // Copy rawdata to lose object reference.
  data = JSON.parse( JSON.stringify( data ) );

  data.text      = this.pluggable._applyModifications( "content", data.text );
  data.metaTitle = this.pluggable._applyModifications( "title", data.metaTitle );

  return data;
};

/**
 * Function to fire the analyzer when all plugins are loaded.
 *
 * @returns {void}
 */
App.prototype.pluginsLoaded = function() {
  this.getData();
  this.runAnalyzer();
};

// ***** PLUGGABLE PUBLIC DSL ***** //

/**
 * Delegates to `YoastSEO.app.pluggable.registerPlugin`
 *
 * @param {string}  pluginName      The name of the plugin to be registered.
 * @param {object}  options         The options object.
 * @param {string}  options.status  The status of the plugin being registered. Can either be "loading" or "ready".
 * @returns {boolean}               Whether or not it was successfully registered.
 */
App.prototype.registerPlugin = function( pluginName, options ) {
  return this.pluggable._registerPlugin( pluginName, options );
};

/**
 * Delegates to `YoastSEO.app.pluggable.ready`
 *
 * @param {string}  pluginName  The name of the plugin to check.
 * @returns {boolean}           Whether or not the plugin is ready.
 */
App.prototype.pluginReady = function( pluginName ) {
  return this.pluggable._ready( pluginName );
};

/**
 * Delegates to `YoastSEO.app.pluggable.reloaded`
 *
 * @param {string} pluginName   The name of the plugin to reload
 * @returns {boolean}           Whether or not the plugin was reloaded.
 */
App.prototype.pluginReloaded = function( pluginName ) {
  return this.pluggable._reloaded( pluginName );
};

/**
 * Delegates to `YoastSEO.app.pluggable.registerModification`
 *
 * @param {string}      modification 		The name of the filter
 * @param {function}    callable 		 	The callable function
 * @param {string}      pluginName 		    The plugin that is registering the modification.
 * @param {number}      priority 		 	(optional) Used to specify the order in which the callables associated with a particular filter are
 called.
 * 									        Lower numbers correspond with earlier execution.
 * @returns 			{boolean}           Whether or not the modification was successfully registered.
 */
App.prototype.registerModification = function( modification, callable, pluginName, priority ) {
  return this.pluggable._registerModification( modification, callable, pluginName, priority );
};

/**
 * Registers a custom assessment for use in the analyzer, this will result in a new line in the analyzer results.
 * The function needs to use the assessmentresult to return an result  based on the contents of the page/posts.
 *
 * Score 0 results in a grey circle if it is not explicitly set by using setscore
 * Scores 0, 1, 2, 3 and 4 result in a red circle
 * Scores 6 and 7 result in a yellow circle
 * Scores 8, 9 and 10 result in a green circle
 *
 * @param {string} name Name of the test.
 * @param {function} assessment The assessment to run
 * @param {string}   pluginName The plugin that is registering the test.
 * @returns {boolean} Whether or not the test was successfully registered.
 */
App.prototype.registerAssessment = function( name, assessment, pluginName ) {
  if ( ! isUndefined( this.seoAssessor ) ) {
    return this.pluggable._registerAssessment( this.defaultSeoAssessor, name, assessment, pluginName ) &&
      this.pluggable._registerAssessment( this.cornerStoneSeoAssessor, name, assessment, pluginName );
  }
};

module.exports = App;