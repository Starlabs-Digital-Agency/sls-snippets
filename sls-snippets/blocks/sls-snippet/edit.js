(function(wp){
  var el = wp.element.createElement;
  var InspectorControls = (wp.blockEditor || wp.editor).InspectorControls;
  var useBlockProps = (wp.blockEditor || wp.editor).useBlockProps;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;
  var ToggleControl = wp.components.ToggleControl;

  function Edit(props){
    var attributes = props.attributes || {};
    var setAttributes = props.setAttributes;
    var blockProps = useBlockProps({ className: 'sls-snippet-block' });

    return el('div', blockProps,
      el(InspectorControls, {},
        el(PanelBody, { title: 'Snippet Settings' },
          el(TextControl, { label: 'Snippet ID', value: attributes.id || '', onChange: function(v){ setAttributes({ id: parseInt(v||'0',10) }); } }),
          el(TextControl, { label: 'Height (px)', value: attributes.height || '', onChange: function(v){ setAttributes({ height: v }); } }),
          el(ToggleControl, { label: 'Auto-run', checked: !!attributes.autorun, onChange: function(v){ setAttributes({ autorun: v }); } })
        )
      ),
      el('p', {}, 'SLS Snippet #', attributes.id || '—')
    );
  }

  wp.blocks.registerBlockType('sls/snippet', { edit: Edit, save: function(){ return null; } });
})(window.wp);
