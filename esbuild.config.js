const esbuild = require('esbuild');

// WordPress core registers 'react' and 'react-dom' as script handles that
// put React on window.React / window.ReactDOM. This plugin intercepts
// imports of those module names (plus the 'react-dom/client' subpath used
// for createRoot, which the react-dom 18 UMD build also exposes on
// window.ReactDOM) and resolves them to a virtual module that just
// re-exports the global. No React code is ever bundled.
const globalExternals = {
  react: 'React',
  'react-dom': 'ReactDOM',
  'react-dom/client': 'ReactDOM',
};

/** @type {import('esbuild').Plugin} */
const externalGlobalsPlugin = {
  name: 'external-globals',
  setup(build) {
    const filter = new RegExp(`^(${Object.keys(globalExternals).join('|').replace(/\//g, '\\/')})$`);

    build.onResolve({ filter }, (args) => ({
      path: args.path,
      namespace: 'external-global',
    }));

    build.onLoad({ filter: /.*/, namespace: 'external-global' }, (args) => ({
      contents: `module.exports = window.${globalExternals[args.path]};`,
      loader: 'js',
    }));
  },
};

const isWatch = process.argv.includes('--watch');

const jsOptions = {
  entryPoints: ['assets/src/editor/index.jsx'],
  bundle: true,
  outfile: 'assets/dist/editor.js',
  format: 'iife',
  target: ['es2020'],
  jsx: 'transform',
  jsxFactory: 'React.createElement',
  jsxFragment: 'React.Fragment',
  plugins: [externalGlobalsPlugin],
  sourcemap: true,
  minify: !isWatch,
  logLevel: 'info',
};

const cssOptions = {
  entryPoints: ['assets/src/admin.css'],
  bundle: true,
  outfile: 'assets/dist/admin.css',
  minify: !isWatch,
  logLevel: 'info',
};

// Separate from jsOptions on purpose: this file only ever uses window.wp.*
// globals already loaded by the block editor screen (wp-element,
// wp-plugins, wp-edit-post), never React directly, so it doesn't need the
// externalGlobalsPlugin - and it ships to a screen the three-pane editor
// bundle never loads on, so it stays its own small file rather than
// growing editor.js for every visitor of the classic Page/Post screen too.
const gutenbergPanelOptions = {
  entryPoints: ['assets/src/gutenberg-panel.js'],
  bundle: true,
  outfile: 'assets/dist/gutenberg-panel.js',
  format: 'iife',
  target: ['es2020'],
  sourcemap: true,
  minify: !isWatch,
  logLevel: 'info',
};

async function run() {
  if (isWatch) {
    const jsCtx = await esbuild.context(jsOptions);
    const cssCtx = await esbuild.context(cssOptions);
    const gutenbergCtx = await esbuild.context(gutenbergPanelOptions);
    await Promise.all([jsCtx.watch(), cssCtx.watch(), gutenbergCtx.watch()]);
    console.log('Watching for changes...');
  } else {
    await Promise.all([
      esbuild.build(jsOptions),
      esbuild.build(cssOptions),
      esbuild.build(gutenbergPanelOptions),
    ]);
  }
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
