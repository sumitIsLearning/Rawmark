// allow-scripts + allow-same-origin together would let the framed content
// strip its own sandbox and reach the parent wp-admin DOM and the editing
// admin's session. Never add allow-same-origin or allow-top-navigation here.
const SANDBOX = 'allow-scripts allow-forms allow-popups allow-modals';

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
    // srcdoc is the already-rendered document from the /preview REST
    // endpoint (see api-client.js's renderPreview) - this no longer builds
    // its own document. Scroll restoration still needs a static script
    // appended after the server's own content, same trick as before.
    update(srcdoc) {
      const persist =
        '<script>addEventListener("scroll",function(){parent.postMessage({__rawmarkScroll:scrollY},"*")});' +
        'addEventListener("load",function(){try{scrollTo(0,' +
        scrollY +
        ')}catch(e){}})</script>';

      iframe.srcdoc = srcdoc.includes('</body>')
        ? srcdoc.replace('</body>', persist + '</body>')
        : srcdoc + persist;
    },
    resetScroll() {
      scrollY = 0;
    },
    destroy() {
      window.removeEventListener('message', onMessage);
    },
  };
}
