(function (blocks, element, i18n, components) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = (wp.blockEditor && wp.blockEditor.InspectorControls) || (wp.editor && wp.editor.InspectorControls);
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;

    blocks.registerBlockType('dragon-zap-affiliate/related-courses', {
        title: __('Dragon Zap Related Courses', 'dragon-zap-affiliate'),
        description: __('Display Dragon Zap courses that are related to the current post.', 'dragon-zap-affiliate'),
        icon: 'welcome-learn-more',
        category: 'widgets',
        supports: {
            html: false,
        },
        attributes: {
            title: {
                type: 'string',
                default: __('Recommended Courses', 'dragon-zap-affiliate'),
            },
            showTitle: {
                type: 'boolean',
                default: true,
            },
        },
        edit: function (props) {
            var attributes = props.attributes;

            return [
                el(InspectorControls, { key: 'controls' },
                    el(PanelBody, { title: __('Display Settings', 'dragon-zap-affiliate'), initialOpen: true },
                        el(ToggleControl, {
                            label: __('Show heading', 'dragon-zap-affiliate'),
                            checked: attributes.showTitle,
                            onChange: function (value) {
                                props.setAttributes({ showTitle: value });
                            },
                        }),
                        attributes.showTitle && el(TextControl, {
                            label: __('Heading text', 'dragon-zap-affiliate'),
                            value: attributes.title,
                            onChange: function (value) {
                                props.setAttributes({ title: value });
                            },
                        })
                    )
                ),
                el('div', { className: 'dragon-zap-affiliate-related-courses-block-preview', key: 'preview' },
                    el('p', {}, __('Related courses will be shown on single posts after the content.', 'dragon-zap-affiliate'))
                )
            ];
        },
        save: function () {
            return null;
        },
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components);
