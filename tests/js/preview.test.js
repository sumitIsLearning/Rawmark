import { describe, expect, it } from 'vitest';
import { createPreview } from '../../assets/src/editor/preview.js';

describe('preview iframe sandbox', () => {
  it('never includes allow-same-origin or allow-top-navigation', () => {
    const container = document.createElement('div');
    const preview = createPreview(container);
    const sandbox = preview.iframe.getAttribute('sandbox');

    expect(sandbox).not.toContain('allow-same-origin');
    expect(sandbox).not.toContain('allow-top-navigation');
    expect(sandbox).toContain('allow-scripts');
    expect(sandbox).toContain('allow-forms');
    expect(sandbox).toContain('allow-popups');
    expect(sandbox).toContain('allow-modals');
  });
});

describe('createPreview.update', () => {
  it('sets the iframe srcdoc directly from the given HTML string', () => {
    const container = document.createElement('div');
    const preview = createPreview(container);

    preview.update('<!DOCTYPE html><html><body>Hi</body></html>');

    // update() inserts a scroll-persistence script immediately before
    // </body>, so the substring right around the given content survives
    // even though the exact "<body>Hi</body>" run doesn't.
    expect(preview.iframe.srcdoc).toContain('>Hi<');
    expect(preview.iframe.srcdoc).toContain('</body>');
  });
});
