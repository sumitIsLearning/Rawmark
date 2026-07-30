import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { EditorView } from '@codemirror/view';
import { tags } from '@lezer/highlight';

// Colors match the Rawmark Editor design (GitHub-light-derived palette).
const highlightStyle = HighlightStyle.define([
  { tag: tags.comment, color: '#6a737d' },
  { tag: tags.string, color: '#032f62' },
  { tag: tags.keyword, color: '#d73a49' },
  { tag: tags.number, color: '#005cc5' },
  { tag: [tags.function(tags.variableName), tags.function(tags.propertyName)], color: '#6f42c1' },
  { tag: tags.tagName, color: '#22863a' },
  { tag: tags.attributeName, color: '#6f42c1' },
  { tag: tags.propertyName, color: '#005cc5' },
  { tag: tags.punctuation, color: '#191919' },
]);

const baseTheme = EditorView.theme({
  '&': {
    height: '100%',
    fontSize: '13px',
    backgroundColor: '#ffffff',
    color: '#191919',
  },
  '.cm-content': {
    fontFamily: 'ui-monospace, "SF Mono", "JetBrains Mono", "Roboto Mono", Menlo, Consolas, monospace',
    padding: '12px 16px 40px 12px',
    caretColor: '#191919',
  },
  '.cm-gutters': {
    backgroundColor: '#fbfbfa',
    color: '#b6bac2',
    border: 'none',
    borderRight: '1px solid #eeede9',
  },
  '.cm-lineNumbers .cm-gutterElement': {
    fontFamily: 'ui-monospace, "SF Mono", "JetBrains Mono", "Roboto Mono", Menlo, Consolas, monospace',
    fontSize: '13px',
    padding: '0 8px 0 0',
  },
  '.cm-activeLine': { backgroundColor: 'rgba(0,0,0,0.02)' },
  '.cm-activeLineGutter': { backgroundColor: 'transparent' },
  '&.cm-focused .cm-selectionBackground, .cm-selectionBackground': { backgroundColor: '#cfe3fb' },
  '&.cm-focused': { outline: 'none' },
});

export const editorTheme = [baseTheme, syntaxHighlighting(highlightStyle)];
