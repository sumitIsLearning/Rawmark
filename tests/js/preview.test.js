import { describe, expect, it } from 'vitest';
import { composeSrcDoc, createPreview } from '../../assets/src/editor/preview.js';

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

describe('compose-time escaping', () => {
  it('escapes a literal </script> inside the JS payload', () => {
    const doc = composeSrcDoc({ html: '', css: '', js: 'var s = "</script>";' });

    expect(doc).not.toMatch(/<\/script>";/);
    expect(doc).toContain('<\\/script>');
  });

  it('escapes a literal </style> inside the CSS payload', () => {
    const doc = composeSrcDoc({ html: '', css: 'body{}</style><script>alert(1)</script>', js: '' });

    expect(doc).not.toContain('</style><script>alert(1)</script>');
    expect(doc).toContain('<\\/style>');
  });
});
