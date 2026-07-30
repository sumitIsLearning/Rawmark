import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { editorTheme } from './theme';

const languages = {
  html: () => html(),
  css: () => css(),
  js: () => javascript(),
};

// CodeMirror 6 owns its own DOM and state/transaction system, so it's
// mounted once into a ref'd container and kept uncontrolled rather than
// put under React's render cycle. The bridge is two one-way paths:
// updateListener -> onChange/onCursor (into React state) and setValue ->
// a CM transaction (for externally-driven changes, e.g. the initial load).
export function createPane(container, { language, initialValue = '', onChange, onCursor }) {
  const state = EditorState.create({
    doc: initialValue,
    extensions: [
      lineNumbers(),
      history(),
      keymap.of([...defaultKeymap, ...historyKeymap]),
      languages[language](),
      editorTheme,
      EditorView.lineWrapping,
      EditorView.updateListener.of((update) => {
        if (update.docChanged && typeof onChange === 'function') {
          onChange(update.state.doc.toString());
        }
        if ((update.docChanged || update.selectionSet) && typeof onCursor === 'function') {
          const pos = update.state.selection.main.head;
          const line = update.state.doc.lineAt(pos);
          onCursor(line.number, pos - line.from + 1);
        }
      }),
    ],
  });

  const view = new EditorView({ state, parent: container });

  return {
    view,
    getValue: () => view.state.doc.toString(),
    setValue(value) {
      const current = view.state.doc.toString();

      if (current === value) {
        return;
      }

      view.dispatch({
        changes: { from: 0, to: current.length, insert: value },
      });
    },
    focus: () => view.focus(),
    remeasure: () => view.requestMeasure(),
    destroy: () => view.destroy(),
  };
}
