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

    var useBlockProps = blockEditor && typeof blockEditor.useBlockProps === 'function'
        ? blockEditor.useBlockProps
        : function () {
            return {};
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
            showImages: {
                type: 'boolean',
                default: true,
            },
            showDescription: {
                type: 'boolean',
                default: true,
            },
            showPrice: {
                type: 'boolean',
                default: true,
            },
            backgroundColor: {
                type: 'string',
                default: '',
            },
            textColor: {
                type: 'string',
                default: '',
            },
            accentColor: {
                type: 'string',
                default: '',
            },
            borderColor: {
                type: 'string',
                default: '',
            },
            customClass: {
                type: 'string',
                default: '',
            },
        },
        edit: function (props) {
            var attributes = props.attributes;
            var blockProps = useBlockProps();

            if (!blockProps.className && props.className) {
                blockProps.className = props.className;
            }

            var previewProps = Object.assign({ key: 'preview' }, blockProps);
            var previewClass = 'dragon-zap-affiliate-related-courses-block-preview';

            if (previewProps.className) {
                previewProps.className += ' ' + previewClass;
            } else {
                previewProps.className = previewClass;
            }

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
                        }),
                        el(ToggleControl, {
                            label: __('Show course images', 'dragon-zap-affiliate'),
                            checked: attributes.showImages,
                            onChange: function (value) {
                                props.setAttributes({ showImages: value });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Show descriptions', 'dragon-zap-affiliate'),
                            checked: attributes.showDescription,
                            onChange: function (value) {
                                props.setAttributes({ showDescription: value });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Show prices', 'dragon-zap-affiliate'),
                            checked: attributes.showPrice,
                            onChange: function (value) {
                                props.setAttributes({ showPrice: value });
                            },
                        })
                    ),
                    el(PanelBody, { title: __('Style Settings', 'dragon-zap-affiliate'), initialOpen: false },
                        el(TextControl, {
                            label: __('Background color', 'dragon-zap-affiliate'),
                            placeholder: '#ffffff',
                            value: attributes.backgroundColor,
                            onChange: function (value) {
                                props.setAttributes({ backgroundColor: value });
                            },
                        }),
                        el(TextControl, {
                            label: __('Text color', 'dragon-zap-affiliate'),
                            placeholder: '#0f172a',
                            value: attributes.textColor,
                            onChange: function (value) {
                                props.setAttributes({ textColor: value });
                            },
                        }),
                        el(TextControl, {
                            label: __('Link & accent color', 'dragon-zap-affiliate'),
                            placeholder: '#1d4ed8',
                            value: attributes.accentColor,
                            onChange: function (value) {
                                props.setAttributes({ accentColor: value });
                            },
                        }),
                        el(TextControl, {
                            label: __('Border color', 'dragon-zap-affiliate'),
                            placeholder: '#e2e8f0',
                            value: attributes.borderColor,
                            onChange: function (value) {
                                props.setAttributes({ borderColor: value });
                            },
                        }),
                        el(TextControl, {
                            label: __('Additional CSS classes', 'dragon-zap-affiliate'),
                            value: attributes.customClass,
                            onChange: function (value) {
                                props.setAttributes({ customClass: value });
                            },
                        })
                    )
                ),
                el('div', previewProps,
                    el('p', {}, __('Related courses will be shown on single posts after the content or wherever you place this block.', 'dragon-zap-affiliate'))
                )
            ];
        },
        save: function () {
            return null;
        },
    });
})(window.wp || {});
