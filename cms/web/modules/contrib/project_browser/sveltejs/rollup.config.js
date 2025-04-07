import svelte from 'rollup-plugin-svelte';
import commonjs from '@rollup/plugin-commonjs';
import resolve from '@rollup/plugin-node-resolve';
import livereload from 'rollup-plugin-livereload';
import css from 'rollup-plugin-css-only';
import json from '@rollup/plugin-json';
import { writeFileSync } from 'fs';
// cspell:ignore iife

const production = !process.env.ROLLUP_WATCH;

function serve() {
  let server;

  function toExit() {
    if (server) server.kill(0);
  }

  return {
    writeBundle() {
      if (server) return;
      server = require('child_process').spawn('npm', ['run', 'start', '--', '--dev'], {
        stdio: ['ignore', 'inherit', 'inherit'],
        shell: true
      });

      process.on('SIGTERM', toExit);
      process.on('exit', toExit);
    }
  };
}

const nameSpaceTranslate = ({content}) => {
  // Prefix calls to `Drupal.t()` with `window` so Svelte will not
  // rename or move it during compile. This ensures the locale module
  // can parse the JavaScript file to find calls to t().
  const translateRegex = /(Drupal\s*?\.t\()/gm;
  const code = content.replaceAll(translateRegex, 'window.$1');
  return {code};
}

export default {
  input: 'src/main.js',
  output: {
    sourcemap: true,
    format: 'iife',
    name: 'app',
    file: 'public/build/bundle.js'
  },
  onwarn: (warn, warning) => {
    let message = warn.message;
    if (message.includes('drupalSettings')) {
      return;
    }
    if (message.includes('on:blur')) {
      return;
    }
    // Use default for everything else
    warning(warn);
  },
  plugins: [
    json(),
    svelte({
      preprocess: {
        markup: nameSpaceTranslate,
      },
      compilerOptions: {
        // enable run-time checks when not in production
        dev: !production,
        cssHash: ({hash, css, name, filename}) => {
          // Strip all line breaks and whitespace from the CSS string used to
          // calculate hash.
          return `pb-${hash(css ? css.replaceAll(/(\r\n|\n|\r|\s)/gm, '') : '')}`;
        },
      }
    }),
    // we'll extract any component CSS out into
    // a separate file - better for performance
    css({ output:(styles, styleNodes) => {
      let orderedStyles = '';
      // To ensure the CSS files are compiled in the same order on every build,
      // sort alphabetically by the CSS file's path.
      Object.keys(styleNodes)
        .sort()
        .forEach((fromFile) =>  {
          orderedStyles += styleNodes[fromFile];
        });
      writeFileSync('./public/build/bundle.css', orderedStyles);
    }, }),

    // If you have external dependencies installed from
    // npm, you'll most likely need these plugins. In
    // some cases you'll need additional configuration -
    // consult the documentation for details:
    // https://github.com/rollup/plugins/tree/master/packages/commonjs
    resolve({
      browser: true,
      dedupe: ['svelte']
    }),
    commonjs(),

    // In dev mode, call `npm run start` once
    // the bundle has been generated
    !production && serve(),

    // Watch the `public` directory and refresh the
    // browser on changes when not in production
    !production && livereload('public'),
  ],
  watch: {
    clearScreen: false
  }
};
