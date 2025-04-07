var CopyPlugin = require('copy-webpack-plugin');

var path = require('path');

module.exports = {
  mode: 'production',
  entry: './src/bpmn-modeller.js',
  output: {
    path: path.resolve(__dirname, '../js'),
    filename: 'bpmn-modeller.js'
  },
  plugins: [
    new CopyPlugin({
      patterns: [
        { from: 'node_modules/@bpmn-io/element-template-chooser/dist/element-template-chooser.css', to: '../css' },
        { from: 'node_modules/@bpmn-io/properties-panel/dist/assets', to: '../css' },
        { from: 'node_modules/bpmn-js/dist/assets', to: '../css' },
        { from: 'node_modules/bpmn-js-element-templates/dist/assets', to: '../css' },
      ]
    })
  ]
};
