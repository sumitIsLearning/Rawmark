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

async function run() {
  if (isWatch) {
    const jsCtx = await esbuild.context(jsOptions);
    const cssCtx = await esbuild.context(cssOptions);
    await Promise.all([jsCtx.watch(), cssCtx.watch()]);
    console.log('Watching for changes...');
  } else {
    await Promise.all([esbuild.build(jsOptions), esbuild.build(cssOptions)]);
  }
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
