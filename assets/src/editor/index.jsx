import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { createPane } from './panes';
import { createPreview } from './preview';
import { getPage, savePage, createSnippet } from './api-client';
import { Icon } from './icons';

const PANES = [
  { key: 'html', label: 'HTML', language: 'html' },
  { key: 'css', label: 'CSS', language: 'css' },
  { key: 'js', label: 'JS', language: 'js' },
];

const VIEWPORTS = {
  mobile: { w: '390px', h: '760px', pad: '32px 24px', radius: '20px', align: 'flex-start', label: 'Mobile' },
  tablet: { w: '768px', h: '940px', pad: '32px 24px', radius: '14px', align: 'flex-start', label: 'Tablet' },
  desktop: { w: '100%', h: '100%', pad: '0', radius: '0', align: 'stretch', label: 'Desktop' },
};

const LAYOUTS = ['code', 'split', 'preview'];

const SAVE_STATE_META = {
  saved: { color: '#2f9e44' },
  unsaved: { color: '#b8860b' },
  saving: { color: '#0075de' },
  failed: { color: '#d4453f' },
};

// UI-only hints for the status bar - the server (Storage/Source.php) is
// the real, authoritative enforcement: 256KB soft warning per pane, 1MB
// hard cap per pane, 2MB hard cap combined. This combined-size indicator
// just gives the developer an early signal before they hit save.
const SIZE_WARN_BYTES = 262144;
const SIZE_BAD_BYTES = 1048576;

function fmtSize(bytes) {
  if (bytes < 1024) {
    return bytes + ' B';
  }
  return (bytes / 1024).toFixed(bytes < 102400 ? 1 : 0) + ' KB';
}

function ago(timestamp) {
  if (!timestamp) {
    return '';
  }
  const seconds = Math.round((Date.now() - timestamp) / 1000);
  if (seconds < 45) {
    return 'just now';
  }
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) {
    return minutes + 'm ago';
  }
  return Math.round(minutes / 60) + 'h ago';
}

// Simple bracket-balance check across all three panes combined - a cheap
// "something's probably wrong" signal, not a real linter.
function countLintIssues(html, css, js) {
  const all = html + '\n' + css + '\n' + js;
  const pairs = { ')': '(', ']': '[', '}': '{' };
  const stack = [];
  let bad = 0;
  let inString = null;

  for (let i = 0; i < all.length; i++) {
    const ch = all[i];
    if (inString) {
      if (ch === inString && all[i - 1] !== '\\') {
        inString = null;
      }
      continue;
    }
    if (ch === '"' || ch === "'" || ch === '`') {
      inString = ch;
      continue;
    }
    if (ch === '(' || ch === '[' || ch === '{') {
      stack.push(ch);
    } else if (pairs[ch]) {
      if (stack.pop() !== pairs[ch]) {
        bad++;
      }
    }
  }

  return bad + stack.length;
}

function EditorApp({ postId, objectType }) {
  const [title, setTitle] = useState('');
  const [status, setStatus] = useState('draft');
  const [source, setSource] = useState({ html: '', css: '', js: '' });
  const [active, setActiveState] = useState('html');
  const [modified, setModified] = useState({ html: false, css: false, js: false });
  const [layout, setLayout] = useState('split');
  const [viewport, setViewport] = useState('desktop');
  const [splitPct, setSplitPct] = useState(50);
  const [saveState, setSaveState] = useState('loading');
  const [savedAgo, setSavedAgo] = useState('');
  const [error, setError] = useState('');
  const [cursor, setCursor] = useState({ ln: 1, col: 1 });
  const [snippetMsg, setSnippetMsg] = useState('');
  const [snippetError, setSnippetError] = useState('');
  const [permalink, setPermalink] = useState('');

  const paneRefs = useRef({});
  const paneInstances = useRef({});
  const previewRef = useRef(null);
  const previewInstance = useRef(null);
  const activeRef = useRef(active);
  const sourceRef = useRef(source);
  const titleRef = useRef(title);
  const statusRef = useRef(status);
  const savedAtRef = useRef(0);
  const previewTimer = useRef(null);

  activeRef.current = active;
  sourceRef.current = source;
  titleRef.current = title;
  statusRef.current = status;

  const updatePreviewNow = useCallback((next) => {
    if (previewInstance.current) {
      previewInstance.current.update(next);
    }
  }, []);

  const schedulePreview = useCallback(
    (next) => {
      clearTimeout(previewTimer.current);
      previewTimer.current = setTimeout(() => updatePreviewNow(next), 300);
    },
    [updatePreviewNow]
  );

  const setActive = useCallback((key) => {
    setActiveState(key);
    requestAnimationFrame(() => {
      const pane = paneInstances.current[key];
      if (pane) {
        pane.remeasure();
        pane.focus();
      }
    });
  }, []);

  // Mount the preview iframe once.
  useEffect(() => {
    if (previewRef.current && !previewInstance.current) {
      previewInstance.current = createPreview(previewRef.current);
    }
    return () => {
      if (previewInstance.current) {
        previewInstance.current.destroy();
      }
    };
  }, []);

  // Mount each CodeMirror pane once, uncontrolled, all three kept mounted
  // (visibility toggled via CSS) so switching tabs never loses undo
  // history or scroll position.
  useEffect(() => {
    PANES.forEach(({ key, language }) => {
      const container = paneRefs.current[key];
      if (!container || paneInstances.current[key]) {
        return;
      }
      paneInstances.current[key] = createPane(container, {
        language,
        initialValue: sourceRef.current[key],
        onChange: (value) => {
          setSource((prev) => {
            const next = { ...prev, [key]: value };
            schedulePreview(next);
            return next;
          });
          setModified((prev) => ({ ...prev, [key]: true }));
          setSaveState((prev) => (prev === 'saving' ? prev : 'unsaved'));
        },
        onCursor: (line, col) => {
          if (activeRef.current === key) {
            setCursor({ ln: line, col });
          }
        },
      });
    });

    const instances = paneInstances.current;
    return () => {
      Object.values(instances).forEach((pane) => pane.destroy());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [schedulePreview]);

  // Load the saved source and push it into the panes + preview.
  useEffect(() => {
    let cancelled = false;

    getPage(postId)
      .then((data) => {
        if (cancelled) {
          return;
        }
        const next = { html: data.html || '', css: data.css || '', js: data.js || '' };
        setTitle(data.title || '');
        setStatus(data.status || 'draft');
        setPermalink(data.permalink || '');
        setSource(next);
        setSaveState('saved');
        savedAtRef.current = Date.now();
        setSavedAgo('just now');

        PANES.forEach(({ key }) => {
          if (paneInstances.current[key]) {
            paneInstances.current[key].setValue(next[key]);
          }
        });
        updatePreviewNow(next);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err.message);
          setSaveState('failed');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [postId, updatePreviewNow]);

  // "Saved Xm ago" ticks forward without a fresh save.
  useEffect(() => {
    const timer = setInterval(() => {
      if (savedAtRef.current) {
        setSavedAgo(ago(savedAtRef.current));
      }
    }, 20000);
    return () => clearInterval(timer);
  }, []);

  const doSave = useCallback(
    (nextStatus) => {
      setSaveState((prev) => {
        if (prev === 'saving') {
          return prev;
        }
        return 'saving';
      });
      setError('');

      // `status` is sent only when the save is actually changing it (i.e.
      // Publish). Echoing the current status back is both pointless and
      // wrong: a brand-new page is an `auto-draft`, which the server
      // deliberately refuses as an input status - accepting it would let a
      // saved page stay garbage-collectable. Omitting the field lets the
      // server promote auto-draft to draft on its own.
      const payload = {
        title: titleRef.current,
        html: sourceRef.current.html,
        css: sourceRef.current.css,
        js: sourceRef.current.js,
      };

      if (nextStatus) {
        payload.status = nextStatus;
      }

      savePage(postId, payload)
        .then((data) => {
          setStatus(data.status);
          setPermalink(data.permalink || '');
          setSaveState('saved');
          savedAtRef.current = Date.now();
          setSavedAgo('just now');
          setModified({ html: false, css: false, js: false });
        })
        .catch((err) => {
          setError(err.message);
          setSaveState('failed');
        });
    },
    [postId]
  );

  const doSaveAsSnippet = useCallback(() => {
    if (saveState === 'unsaved') {
      const proceed = window.confirm(
        'This page has unsaved changes. The snippet will be created from the last saved version, not what you see now. Continue?'
      );
      if (!proceed) {
        return;
      }
    }

    const name = window.prompt('Name this snippet:', titleRef.current || '');
    if (!name) {
      return;
    }

    setSnippetError('');
    createSnippet(postId, name)
      .then(() => {
        setSnippetMsg(`Saved as snippet "${name}"`);
        setTimeout(() => setSnippetMsg(''), 4000);
      })
      .catch((err) => {
        setSnippetError(err.message);
        setTimeout(() => setSnippetError(''), 6000);
      });
  }, [postId, saveState]);

  const cycleLayout = useCallback(() => {
    setLayout((prev) => LAYOUTS[(LAYOUTS.indexOf(prev) + 1) % LAYOUTS.length]);
  }, []);

  // Keyboard shortcuts: Cmd/Ctrl+S save, Cmd/Ctrl+1/2/3 switch pane,
  // Cmd/Ctrl+\ cycle layout.
  useEffect(() => {
    const onKeyDown = (event) => {
      const mod = event.metaKey || event.ctrlKey;
      if (!mod) {
        return;
      }
      const key = event.key.toLowerCase();
      if (key === 's') {
        event.preventDefault();
        doSave();
      } else if (key === '1') {
        event.preventDefault();
        setActive('html');
      } else if (key === '2') {
        event.preventDefault();
        setActive('css');
      } else if (key === '3') {
        event.preventDefault();
        setActive('js');
      } else if (key === '\\') {
        event.preventDefault();
        cycleLayout();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [doSave, setActive, cycleLayout]);

  const onDragStart = (event) => {
    event.preventDefault();
    const container = event.currentTarget.parentElement;
    const rect = container.getBoundingClientRect();
    const onMove = (moveEvent) => {
      let pct = ((moveEvent.clientX - rect.left) / rect.width) * 100;
      pct = Math.max(22, Math.min(78, pct));
      setSplitPct(pct);
    };
    const onUp = () => {
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    };
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
  };

  const onInsertMedia = () => {
    if (!window.wp || !window.wp.media) {
      return;
    }

    const frame = window.wp.media({
      title: 'Insert Media',
      button: { text: 'Insert' },
      library: { type: ['image', 'video'] },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const pane = paneInstances.current[activeRef.current];
      if (!pane) {
        return;
      }

      const isVideo = attachment.type === 'video';
      const snippet =
        activeRef.current === 'html'
          ? isVideo
            ? `<video src="${attachment.url}" controls></video>`
            : `<img src="${attachment.url}" alt="">`
          : attachment.url;

      pane.insertAtCursor(snippet);
    });

    frame.open();
  };

  const onRefresh = () => {
    if (previewInstance.current) {
      previewInstance.current.resetScroll();
    }
    updatePreviewNow(sourceRef.current);
  };

  const vp = VIEWPORTS[viewport];
  // These must stay 'block', not 'flex'. Both sections are flex *items* of
  // .rawmark-editor__split, but their own inner boxes size as normal blocks -
  // making them flex containers leaves the inner wrapper with no flex-grow, so
  // it shrink-to-fits and the preview frame's width:100% resolves against a
  // collapsed box instead of the full pane.
  const codeDisplay = layout === 'preview' ? 'none' : 'block';
  const previewDisplay = layout === 'code' ? 'none' : 'block';
  const dividerDisplay = layout === 'split' ? 'block' : 'none';
  const codeBasis = layout === 'code' ? '100%' : layout === 'split' ? splitPct + '%' : '0%';

  const sv = SAVE_STATE_META[saveState] || SAVE_STATE_META.saved;
  const saveText =
    saveState === 'saved'
      ? 'Saved · ' + savedAgo
      : saveState === 'unsaved'
        ? 'Unsaved changes'
        : saveState === 'saving'
          ? 'Saving…'
          : 'Save failed — retry';

  const lintN = countLintIssues(source.html, source.css, source.js);
  const lintOk = lintN === 0;
  const sizeBytes = new Blob([source.html + source.css + source.js]).size;
  const sizeBad = sizeBytes > SIZE_BAD_BYTES;
  const sizeWarn = sizeBytes > SIZE_WARN_BYTES;
  const sizeColor = sizeBad ? '#d4453f' : sizeWarn ? '#b8860b' : '#6b6b6b';

  const publishState =
    status === 'publish'
      ? { dot: '#2f9e44', label: 'Published' }
      : saveState === 'unsaved'
        ? { dot: '#b8860b', label: (status[0].toUpperCase() + status.slice(1)) + ' · edited' }
        : { dot: '#8b8b86', label: status[0].toUpperCase() + status.slice(1) };

  return (
    <div className="rawmark-editor">
      <header className="rawmark-editor__topbar">
        <div className="rawmark-editor__brand">
          <span className="rawmark-editor__logo" aria-hidden="true">R</span>
          <span className="rawmark-editor__wordmark">rawmark</span>
          <span className="rawmark-editor__sep" aria-hidden="true">/</span>
          <input
            className="rawmark-editor__title-input"
            value={title}
            placeholder="Untitled page"
            spellCheck="false"
            onChange={(event) => {
              setTitle(event.target.value);
              setSaveState((prev) => (prev === 'saving' ? prev : 'unsaved'));
            }}
          />
        </div>
        <div className="rawmark-editor__topbar-right">
          <span className="rawmark-editor__status-pill">
            <span className="rawmark-editor__dot" style={{ background: publishState.dot }} />
            {publishState.label}
          </span>
          {snippetMsg && (
            <span className="rawmark-editor__statusbar-item" style={{ color: '#2f9e44' }}>
              {snippetMsg}
              {' · '}
              <a href={window.rawmarkEditor && window.rawmarkEditor.snippetsUrl} target="_blank" rel="noreferrer">
                View
              </a>
            </span>
          )}
          {snippetError && (
            <span className="rawmark-editor__statusbar-item" style={{ color: '#d4453f' }}>
              {snippetError}
            </span>
          )}
          {objectType === 'page' && (
            <button
              type="button"
              className="rawmark-editor__btn rawmark-editor__btn--ghost"
              onClick={doSaveAsSnippet}
            >
              Save as Snippet
            </button>
          )}
          <button type="button" className="rawmark-editor__btn rawmark-editor__btn--ghost" onClick={() => doSave()}>
            Save draft
          </button>
          <button
            type="button"
            className="rawmark-editor__btn rawmark-editor__btn--primary"
            onClick={() => doSave('publish')}
          >
            {status === 'publish' ? 'Update' : 'Publish'}
          </button>
        </div>
      </header>

      <div className="rawmark-editor__toolbar">
        <div role="tablist" className="rawmark-editor__tabs">
          {PANES.map(({ key, label }) => {
            const on = active === key;
            return (
              <button
                key={key}
                role="tab"
                aria-selected={on}
                className={'rm-tab rawmark-editor__tab' + (on ? ' rawmark-editor__tab--active' : '')}
                onClick={() => setActive(key)}
              >
                <span className="rawmark-editor__tab-label">{label}</span>
                {modified[key] && <span className="rawmark-editor__tab-dot" aria-hidden="true" />}
              </button>
            );
          })}
        </div>

        <div className="rawmark-editor__toolbar-right">
          <button
            type="button"
            title="Insert Media"
            aria-label="Insert Media"
            className="rm-icon-btn rawmark-editor__icon-btn"
            onClick={onInsertMedia}
          >
            <Icon name="image" />
          </button>

          <div className="rawmark-editor__seg">
            {Object.keys(VIEWPORTS).map((key) => (
              <button
                key={key}
                type="button"
                title={VIEWPORTS[key].label}
                aria-label={VIEWPORTS[key].label}
                className={'rm-seg rawmark-editor__seg-btn' + (viewport === key ? ' rawmark-editor__seg-btn--active' : '')}
                onClick={() => setViewport(key)}
              >
                <Icon name={key} />
              </button>
            ))}
          </div>

          <div className="rawmark-editor__seg">
            <button
              type="button"
              title="Code only"
              aria-label="Code only"
              className={'rm-seg rawmark-editor__seg-btn' + (layout === 'code' ? ' rawmark-editor__seg-btn--active' : '')}
              onClick={() => setLayout('code')}
            >
              <Icon name="codeonly" />
            </button>
            <button
              type="button"
              title="Split view"
              aria-label="Split view"
              className={'rm-seg rawmark-editor__seg-btn' + (layout === 'split' ? ' rawmark-editor__seg-btn--active' : '')}
              onClick={() => setLayout('split')}
            >
              <Icon name="split" />
            </button>
            <button
              type="button"
              title="Preview only"
              aria-label="Preview only"
              className={'rm-seg rawmark-editor__seg-btn' + (layout === 'preview' ? ' rawmark-editor__seg-btn--active' : '')}
              onClick={() => setLayout('preview')}
            >
              <Icon name="previewonly" />
            </button>
          </div>

          <button
            type="button"
            title="Refresh preview"
            aria-label="Refresh preview"
            className="rm-icon-btn rawmark-editor__icon-btn"
            onClick={onRefresh}
          >
            <Icon name="refresh" />
          </button>

          {permalink && (
            <a
              href={permalink}
              target="_blank"
              rel="noreferrer"
              title="View on the live site"
              aria-label="View on the live site"
              className="rm-icon-btn rawmark-editor__icon-btn"
            >
              <Icon name="external" />
            </a>
          )}
        </div>
      </div>

      <div className="rawmark-editor__split">
        <section aria-label="Code editor" className="rawmark-editor__code" style={{ display: codeDisplay, flexBasis: codeBasis }}>
          {PANES.map(({ key }) => (
            <div
              key={key}
              className="rawmark-editor__pane-cm"
              style={{ display: active === key ? 'block' : 'none' }}
              ref={(el) => {
                paneRefs.current[key] = el;
              }}
            />
          ))}
        </section>

        <div
          className="rawmark-editor__divider"
          style={{ display: dividerDisplay }}
          title="Drag to resize · double-click to reset"
          onMouseDown={onDragStart}
          onDoubleClick={() => setSplitPct(50)}
        >
          <span aria-hidden="true" />
        </div>

        <section aria-label="Live preview" className="rawmark-editor__preview-pane" style={{ display: previewDisplay }}>
          <div className="rawmark-editor__frame-wrap" style={{ alignItems: vp.align, padding: vp.pad }}>
            <div
              className="rawmark-editor__frame"
              style={{ width: vp.w, height: vp.h, borderRadius: vp.radius }}
              ref={previewRef}
            />
          </div>
        </section>
      </div>

      <footer className="rawmark-editor__statusbar">
        <span className="rawmark-editor__statusbar-item" style={{ color: sv.color }}>
          <span className="rawmark-editor__dot" style={{ background: sv.color }} />
          {saveText}
        </span>
        <span className="rawmark-editor__statusbar-sep" />
        <button
          type="button"
          className="rawmark-editor__statusbar-item rawmark-editor__lint"
          style={{ color: lintOk ? undefined : '#b8860b' }}
          onClick={() => paneInstances.current[active] && paneInstances.current[active].focus()}
        >
          <Icon name={lintOk ? 'check' : 'warn'} />
          {lintOk ? 'No issues' : lintN + (lintN === 1 ? ' issue' : ' issues')}
        </button>
        <span className="rawmark-editor__statusbar-sep" />
        <span className="rawmark-editor__statusbar-item">Ln {cursor.ln}, Col {cursor.col}</span>
        <span style={{ marginLeft: 'auto' }} />
        {error && <span className="rawmark-editor__statusbar-item" style={{ color: '#d4453f' }} title={error}>{error}</span>}
        <span className="rawmark-editor__statusbar-item" style={{ color: sizeColor }} title="Combined size of HTML + CSS + JS">
          {fmtSize(sizeBytes)}
        </span>
        <span className="rawmark-editor__statusbar-sep" />
        <span className="rawmark-editor__statusbar-item">
          <Icon name={viewport} />
          {vp.label}
        </span>
      </footer>
    </div>
  );
}

const root = document.getElementById('rawmark-editor-root');

if (root) {
  const postId = parseInt(root.dataset.postId, 10) || 0;
  const objectType = root.dataset.objectType === 'snippet' ? 'snippet' : 'page';
  createRoot(root).render(<EditorApp postId={postId} objectType={objectType} />);
}
