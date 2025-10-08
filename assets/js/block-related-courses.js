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

    var PanelColorSettings = (blockEditor && blockEditor.PanelColorSettings)
        ? blockEditor.PanelColorSettings
        : (components && components.PanelColorSettings ? components.PanelColorSettings : null);

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

            var preventPreviewNavigation = function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
            };

            var escapeForSvg = function (value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            };

            var placeholderLabel = escapeForSvg(__('Course', 'dragon-zap-affiliate'));
            var placeholderSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">' +
                '<rect width="96" height="96" rx="12" fill="#e2e8f0" />' +
                '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="12" fill="#475569" font-family="Arial, sans-serif">' +
                placeholderLabel +
                '</text>' +
                '</svg>';
            var placeholderImage = 'data:image/svg+xml;utf8,' + encodeURIComponent(placeholderSvg);

            var sampleCourses = [
                {
                    title: __('Unity 3D Starter Kit', 'dragon-zap-affiliate'),
                    description: __('Build your first real-time game world while mastering Unity fundamentals.', 'dragon-zap-affiliate'),
                    price: 'USD 19.99',
                },
                {
                    title: __('Master Unreal Blueprints', 'dragon-zap-affiliate'),
                    description: __('Create gameplay systems visually and prototype ideas faster than ever.', 'dragon-zap-affiliate'),
                    price: 'USD 29.99',
                },
                {
                    title: __('Game Design Bootcamp', 'dragon-zap-affiliate'),
                    description: __('Learn to balance mechanics, narrative, and player experience like a pro.', 'dragon-zap-affiliate'),
                    price: 'USD 24.99',
                },
            ];

            var containerClasses = ['dragon-zap-affiliate-related-courses', 'dragon-zap-affiliate-related-courses--block'];

            if (!attributes.showImages) {
                containerClasses.push('dragon-zap-affiliate-related-courses--no-images');
            }

            if (attributes.customClass) {
                containerClasses.push(attributes.customClass);
            }

            var styleVariables = {};

            if (attributes.backgroundColor) {
                styleVariables['--dza-related-bg'] = attributes.backgroundColor;
            }

            if (attributes.textColor) {
                styleVariables['--dza-related-text'] = attributes.textColor;
                styleVariables['--dza-related-muted'] = attributes.textColor;
            }

            if (attributes.accentColor) {
                styleVariables['--dza-related-accent'] = attributes.accentColor;
            }

            if (attributes.borderColor) {
                styleVariables['--dza-related-border'] = attributes.borderColor;
            }

            var headingText = attributes.showTitle
                ? (attributes.title || __('Recommended Courses', 'dragon-zap-affiliate'))
                : '';

            var courseItems = sampleCourses.map(function (course, index) {
                var imageElement = null;

                if (attributes.showImages) {
                    imageElement = el('a', {
                        className: 'dragon-zap-affiliate-related-courses__image-link',
                        href: '#',
                        onClick: preventPreviewNavigation,
                    },
                        el('img', {
                            className: 'dragon-zap-affiliate-related-courses__image',
                            src: placeholderImage,
                            alt: course.title,
                            loading: 'lazy',
                        })
                    );
                }

                var priceElement = attributes.showPrice
                    ? el('div', { className: 'dragon-zap-affiliate-related-courses__price' }, course.price)
                    : null;

                var descriptionElement = attributes.showDescription
                    ? el('p', { className: 'dragon-zap-affiliate-related-courses__description' }, course.description)
                    : null;

                return el('li', { key: 'course-' + index, className: 'dragon-zap-affiliate-related-courses__item' },
                    imageElement,
                    el('div', { className: 'dragon-zap-affiliate-related-courses__content' },
                        el('a', {
                            className: 'dragon-zap-affiliate-related-courses__title',
                            href: '#',
                            onClick: preventPreviewNavigation,
                        }, course.title),
                        priceElement,
                        descriptionElement
                    )
                );
            });

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
                        PanelColorSettings
                            ? el(PanelColorSettings, {
                                title: __('Color settings', 'dragon-zap-affiliate'),
                                colorSettings: [
                                    {
                                        value: attributes.backgroundColor,
                                        onChange: function (value) {
                                            props.setAttributes({ backgroundColor: value || '' });
                                        },
                                        label: __('Background color', 'dragon-zap-affiliate'),
                                    },
                                    {
                                        value: attributes.textColor,
                                        onChange: function (value) {
                                            props.setAttributes({ textColor: value || '' });
                                        },
                                        label: __('Text color', 'dragon-zap-affiliate'),
                                    },
                                    {
                                        value: attributes.accentColor,
                                        onChange: function (value) {
                                            props.setAttributes({ accentColor: value || '' });
                                        },
                                        label: __('Link & accent color', 'dragon-zap-affiliate'),
                                    },
                                    {
                                        value: attributes.borderColor,
                                        onChange: function (value) {
                                            props.setAttributes({ borderColor: value || '' });
                                        },
                                        label: __('Border color', 'dragon-zap-affiliate'),
                                    },
                                ],
                            })
                            : [
                                el(TextControl, {
                                    key: 'backgroundColor',
                                    label: __('Background color', 'dragon-zap-affiliate'),
                                    placeholder: '#ffffff',
                                    value: attributes.backgroundColor,
                                    onChange: function (value) {
                                        props.setAttributes({ backgroundColor: value });
                                    },
                                }),
                                el(TextControl, {
                                    key: 'textColor',
                                    label: __('Text color', 'dragon-zap-affiliate'),
                                    placeholder: '#0f172a',
                                    value: attributes.textColor,
                                    onChange: function (value) {
                                        props.setAttributes({ textColor: value });
                                    },
                                }),
                                el(TextControl, {
                                    key: 'accentColor',
                                    label: __('Link & accent color', 'dragon-zap-affiliate'),
                                    placeholder: '#1d4ed8',
                                    value: attributes.accentColor,
                                    onChange: function (value) {
                                        props.setAttributes({ accentColor: value });
                                    },
                                }),
                                el(TextControl, {
                                    key: 'borderColor',
                                    label: __('Border color', 'dragon-zap-affiliate'),
                                    placeholder: '#e2e8f0',
                                    value: attributes.borderColor,
                                    onChange: function (value) {
                                        props.setAttributes({ borderColor: value });
                                    },
                                }),
                            ],
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
                    el('div', {
                        className: containerClasses.join(' '),
                        style: Object.keys(styleVariables).length ? styleVariables : undefined,
                    },
                        headingText !== ''
                            ? el('h2', { className: 'dragon-zap-affiliate-related-courses__heading' }, headingText)
                            : null,
                        el('ul', { className: 'dragon-zap-affiliate-related-courses__list' }, courseItems)
                    )
                )
            ];
        },
        save: function () {
            return null;
        },
    });
})(window.wp || {});
