(function (wp) {
    wp = wp || {};

    var blocks = wp.blocks;
    var element = wp.element;
    var i18n = wp.i18n;
    var components = wp.components;
    var blockEditor = wp.blockEditor || wp.editor;

    if (!blocks || !blocks.registerBlockType || !element || !element.createElement) {
        return;
    }

    var el = element.createElement;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function (text) {
        return text;
    };

    var InspectorControls = blockEditor && blockEditor.InspectorControls
        ? blockEditor.InspectorControls
        : function (props) {
            return props && props.children ? props.children : null;
        };

    var PanelBody = components && components.PanelBody
        ? components.PanelBody
        : function (props) {
            return el('div', props, props.children);
        };

    var TextControl = components && components.TextControl
        ? components.TextControl
        : function () {
            return null;
        };

    var ToggleControl = components && components.ToggleControl
        ? components.ToggleControl
        : function () {
            return null;
        };

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
})(window.wp || {});
