/**
 * "Edit with Rawmark" panel in the block editor's Document sidebar, for a
 * Page or Post that isn't flagged yet. Plain wp.* globals, not bundled -
 * WordPress already loads react/wp-element/wp-plugins/wp-edit-post on this
 * screen, and this file has nothing else to depend on.
 */
(function () {
  var config = window.rawmarkGutenberg || {};
  var el = window.wp.element.createElement;
  var registerPlugin = window.wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = window.wp.editPost.PluginDocumentSettingPanel;

  function RawmarkPanel() {
    return el(
      PluginDocumentSettingPanel,
      { name: 'rawmark-panel', title: 'Rawmark' },
      el(
        'a',
        { href: config.enableUrl, className: 'button button-primary' },
        'Edit with Rawmark'
      )
    );
  }

  registerPlugin('rawmark-panel', { render: RawmarkPanel });
})();
