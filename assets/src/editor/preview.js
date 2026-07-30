const config = window.rawmarkEditor || {};

// allow-scripts + allow-same-origin together would let the framed content
// strip its own sandbox and reach the parent wp-admin DOM and the editing
// admin's session. Never add allow-same-origin or allow-top-navigation here.
const SANDBOX = 'allow-scripts allow-forms allow-popups allow-modals';

function escapeScript(js) {
  return (js || '').replace(/<\/script/gi, '<\\/script');
}

function escapeStyle(css) {
  return (css || '').replace(/<\/style/gi, '<\\/style');
}

export function composeSrcDoc({ html, css, js }, scrollY = 0) {
  const baseHref = config.homeUrl || '/';
  const safeCss = escapeStyle(css);
  const safeJs = escapeScript(js);
  // Every full srcdoc rebuild reloads the iframe from scratch, which
  // would otherwise reset scroll to the top on every keystroke. This
  // static (non-user-data) script reports scroll position to the parent
  // and restores it on load - safe from the </script> escaping concern
  // since it never contains that literal sequence.
  const persist =
    '<script>addEventListener("scroll",function(){parent.postMessage({__rawmarkScroll:scrollY},"*")});' +
    'addEventListener("load",function(){try{scrollTo(0,' +
    scrollY +
    ')}catch(e){}})</script>';

  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="${baseHref}">
<style>${safeCss}</style>
</head>
<body>
${html || ''}
${persist}
<script>${safeJs}</script>
</body>
</html>`;
}

export function createPreview(container) {
  const iframe = document.createElement('iframe');
  iframe.className = 'rawmark-editor__preview';
  iframe.setAttribute('sandbox', SANDBOX);
  iframe.setAttribute('title', 'Rawmark preview');
  container.appendChild(iframe);

  let scrollY = 0;

  // The sandboxed iframe has an opaque origin, so it can only postMessage
  // with target "*" - checking event.source against this iframe's own
  // contentWindow is what keeps this from trusting a message from
  // anywhere else on the page.
  const onMessage = (event) => {
    if (event.source === iframe.contentWindow && event.data && typeof event.data.__rawmarkScroll === 'number') {
      scrollY = event.data.__rawmarkScroll;
    }
  };
  window.addEventListener('message', onMessage);

  return {
    iframe,
    update(source) {
      iframe.srcdoc = composeSrcDoc(source, scrollY);
    },
    resetScroll() {
      scrollY = 0;
    },
    destroy() {
      window.removeEventListener('message', onMessage);
    },
  };
}
