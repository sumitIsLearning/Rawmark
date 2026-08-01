import { describe, expect, it } from 'vitest';
import { createPane } from '../../assets/src/editor/panes.js';

describe('insertAtCursor', () => {
  it('inserts at the current cursor position and moves the cursor after it', () => {
    const container = document.createElement('div');
    const pane = createPane(container, { language: 'html', initialValue: 'ab' });
    pane.view.dispatch({ selection: { anchor: 1 } });

    pane.insertAtCursor('X');

    expect(pane.getValue()).toBe('aXb');
    expect(pane.view.state.selection.main.head).toBe(2);

    pane.destroy();
  });

  it('replaces a non-empty selection instead of inserting alongside it', () => {
    const container = document.createElement('div');
    const pane = createPane(container, { language: 'html', initialValue: 'abcd' });
    pane.view.dispatch({ selection: { anchor: 1, head: 3 } });

    pane.insertAtCursor('X');

    expect(pane.getValue()).toBe('aXd');

    pane.destroy();
  });
});
