(function (wp) {
    wp = wp || {};

    var blocks = wp.blocks;
    var element = wp.element;
    var i18n = wp.i18n;
    var components = wp.components;
    var blockEditor = wp.blockEditor || wp.editor;

    if (! blocks || ! blocks.registerBlockType || ! element || ! element.createElement) {
        return;
    }

    var settings = window.dragonZapAffiliateSingleCourseBlock || {};
    var strings = settings.strings || {};

    var el = element.createElement;
    var Fragment = element.Fragment || function (props) {
        return props && props.children ? props.children : null;
    };
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function (text) {
        return text;
    };

    var useState = element.useState || function (initial) {
        return [initial, function () {}];
    };
    var useEffect = element.useEffect || function () {};
    var useMemo = element.useMemo || function (factory) {
        return factory();
    };
    var useRef = element.useRef || function (initial) {
        return { current: initial };
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

    var Button = components && components.Button
        ? components.Button
        : function (props) {
            var attrs = Object.assign({}, props);
            var children = attrs.children;
            delete attrs.children;
            return el('button', attrs, children);
        };

    var Spinner = components && components.Spinner
        ? components.Spinner
        : function () {
            return el('span', { className: 'spinner is-active' });
        };

    var useBlockProps = blockEditor && typeof blockEditor.useBlockProps === 'function'
        ? blockEditor.useBlockProps
        : function () {
            return {};
        };

    function getString(key, fallback) {
        if (strings && typeof strings[key] === 'string') {
            return strings[key];
        }

        return fallback;
    }

    function formatCoursePrice(course) {
        if (! course) {
            return '';
        }

        var price = typeof course.price === 'string' ? parseFloat(course.price) : course.price;

        if (! isFinite(price)) {
            return '';
        }

        var formatted = price.toFixed(2);
        var currency = course.currency ? String(course.currency) : (course.courseCurrency ? String(course.courseCurrency) : '');

        return currency ? currency + ' ' + formatted : formatted;
    }

    function buildCoursePreview(attrs) {
        if (! attrs.courseId || attrs.courseId <= 0) {
            return null;
        }

        var title = attrs.courseTitle || '';
        var url = attrs.courseUrl || '';
        var description = attrs.courseDescription || '';
        var image = attrs.showImages !== false ? attrs.courseImage : '';
        var priceText = attrs.showPrice !== false ? formatCoursePrice({ price: attrs.coursePrice, currency: attrs.courseCurrency }) : '';

        var headingText = '';

        if (attrs.showTitle !== false) {
            if (typeof attrs.title === 'string') {
                headingText = attrs.title;
            } else {
                headingText = getString('defaultHeading', __('Featured Course', 'dragon-zap-affiliate'));
            }
        }

        return el(
            'div',
            { className: 'dragon-zap-affiliate-related-courses-block-preview' },
            el(
                'div',
                { className: 'dragon-zap-affiliate-related-courses dragon-zap-affiliate-related-courses--single-block' },
                headingText !== ''
                    ? el('h2', { className: 'dragon-zap-affiliate-related-courses__heading' }, headingText)
                    : null,
                el(
                    'ul',
                    { className: 'dragon-zap-affiliate-related-courses__list' },
                    el(
                        'li',
                        { className: 'dragon-zap-affiliate-related-courses__item' },
                        image
                            ? el(
                                'a',
                                {
                                    className: 'dragon-zap-affiliate-related-courses__image-link',
                                    href: url || '#',
                                },
                                el('img', {
                                    className: 'dragon-zap-affiliate-related-courses__image',
                                    src: image,
                                    alt: title,
                                })
                            )
                            : null,
                        el(
                            'div',
                            { className: 'dragon-zap-affiliate-related-courses__content' },
                            el(
                                'a',
                                {
                                    className: 'dragon-zap-affiliate-related-courses__title',
                                    href: url || '#',
                                },
                                title || getString('selectCourse', __('Sample course title', 'dragon-zap-affiliate'))
                            ),
                            priceText !== ''
                                ? el(
                                    'div',
                                    { className: 'dragon-zap-affiliate-related-courses__price' },
                                    priceText
                                )
                                : null,
                            attrs.showDescription !== false && description !== ''
                                ? el(
                                    'p',
                                    { className: 'dragon-zap-affiliate-related-courses__description' },
                                    description
                                )
                                : null
                        )
                    )
                )
            )
        );
    }

    function createFormData(fields) {
        var formData = new window.FormData();

        Object.keys(fields).forEach(function (key) {
            if (typeof fields[key] !== 'undefined' && fields[key] !== null) {
                formData.append(key, fields[key]);
            }
        });

        return formData;
    }

    blocks.registerBlockType('dragon-zap-affiliate/single-course', {
        title: __('Dragon Zap Single Course', 'dragon-zap-affiliate'),
        description: __('Select and showcase a single Dragon Zap course.', 'dragon-zap-affiliate'),
        icon: 'star-filled',
        category: 'widgets',
        supports: {
            html: false,
        },
        attributes: {
            title: {
                type: 'string',
                default: __('Featured Course', 'dragon-zap-affiliate'),
            },
            showTitle: {
                type: 'boolean',
                default: true,
            },
            courseId: {
                type: 'integer',
                default: 0,
            },
            courseTitle: {
                type: 'string',
                default: '',
            },
            courseUrl: {
                type: 'string',
                default: '',
            },
            courseImage: {
                type: 'string',
                default: '',
            },
            courseDescription: {
                type: 'string',
                default: '',
            },
            coursePrice: {
                type: 'string',
                default: '',
            },
            courseCurrency: {
                type: 'string',
                default: '',
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
            var setAttributes = props.setAttributes;

            var blockProps = useBlockProps();
            var preview = useMemo(function () {
                return buildCoursePreview(attributes);
            }, [
                attributes.courseId,
                attributes.courseTitle,
                attributes.courseUrl,
                attributes.courseImage,
                attributes.courseDescription,
                attributes.coursePrice,
                attributes.courseCurrency,
                attributes.showImages,
                attributes.showDescription,
                attributes.showPrice,
                attributes.showTitle,
                attributes.title,
            ]);

            var searchTermState = useState('');
            var searchTerm = searchTermState[0];
            var setSearchTerm = searchTermState[1];

            var resultsState = useState([]);
            var results = resultsState[0];
            var setResults = resultsState[1];

            var searchingState = useState(false);
            var isSearching = searchingState[0];
            var setIsSearching = searchingState[1];

            var errorState = useState('');
            var errorMessage = errorState[0];
            var setErrorMessage = errorState[1];

            var loadingCourseState = useState(false);
            var isLoadingCourse = loadingCourseState[0];
            var setIsLoadingCourse = loadingCourseState[1];

            var controllerRef = useRef(null);

            useEffect(function () {
                if (! attributes.courseId || attributes.courseId <= 0) {
                    return;
                }

                if (attributes.courseTitle) {
                    return;
                }

                if (! settings.ajaxUrl || ! settings.nonce || typeof window.fetch !== 'function') {
                    return;
                }

                setIsLoadingCourse(true);
                var formData = createFormData({
                    action: 'dza_search_courses',
                    nonce: settings.nonce,
                    course_id: attributes.courseId,
                });

                window.fetch(settings.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (json) {
                        if (! json || ! json.success || ! json.data || ! json.data.courses || ! json.data.courses.length) {
                            return;
                        }

                        var course = json.data.courses[0];

                        setAttributes({
                            courseTitle: course.title || '',
                            courseUrl: course.url || '',
                            courseImage: course.image || '',
                            courseDescription: course.description || '',
                            coursePrice: course.price !== null && typeof course.price !== 'undefined' ? String(course.price) : '',
                            courseCurrency: course.currency || '',
                        });
                    })
                    .catch(function () {
                        return null;
                    })
                    .finally(function () {
                        setIsLoadingCourse(false);
                    });
            }, [attributes.courseId]);

            function handleSearchChange(value) {
                setSearchTerm(value);
                setErrorMessage('');

                if (! value || value.trim().length < 2) {
                    setResults([]);
                    setIsSearching(false);
                    return;
                }

                if (! settings.ajaxUrl || ! settings.nonce || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
                    setErrorMessage(getString('error', __('Unable to search for courses. Please check your site configuration.', 'dragon-zap-affiliate')));
                    return;
                }

                if (controllerRef.current && typeof controllerRef.current.abort === 'function') {
                    controllerRef.current.abort();
                }

                var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
                controllerRef.current = controller;

                setIsSearching(true);

                var formData = createFormData({
                    action: 'dza_search_courses',
                    nonce: settings.nonce,
                    search: value,
                });

                window.fetch(settings.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                    signal: controller ? controller.signal : undefined,
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (json) {
                        if (! json || ! json.success) {
                            setErrorMessage(getString('error', __('Something went wrong. Please try again.', 'dragon-zap-affiliate')));
                            setResults([]);
                            return;
                        }

                        var courses = Array.isArray(json.data && json.data.courses) ? json.data.courses : [];

                        setResults(courses);

                        if (! courses.length) {
                            setErrorMessage(getString('noResults', __('No courses found. Try a different search term.', 'dragon-zap-affiliate')));
                        } else {
                            setErrorMessage('');
                        }
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }

                        setErrorMessage(getString('error', __('Something went wrong. Please try again.', 'dragon-zap-affiliate')));
                        setResults([]);
                    })
                    .finally(function () {
                        setIsSearching(false);
                    });
            }

            function handleCourseSelect(course) {
                if (! course) {
                    return;
                }

                setAttributes({
                    courseId: course.id || 0,
                    courseTitle: course.title || '',
                    courseUrl: course.url || '',
                    courseImage: course.image || '',
                    courseDescription: course.description || '',
                    coursePrice: course.price !== null && typeof course.price !== 'undefined' ? String(course.price) : '',
                    courseCurrency: course.currency || '',
                });

                setResults([]);
                setSearchTerm('');
                setErrorMessage('');
            }

            function clearSelection() {
                setAttributes({
                    courseId: 0,
                    courseTitle: '',
                    courseUrl: '',
                    courseImage: '',
                    courseDescription: '',
                    coursePrice: '',
                    courseCurrency: '',
                });

                setResults([]);
                setSearchTerm('');
                setErrorMessage('');
            }

            var courseSelected = attributes.courseId && attributes.courseId > 0;

            var searchInput = el(TextControl, {
                label: getString('searchCourses', __('Search courses', 'dragon-zap-affiliate')),
                placeholder: getString('searchPlaceholder', __('Start typing to search for a course…', 'dragon-zap-affiliate')),
                value: searchTerm,
                onChange: handleSearchChange,
            });

            var resultList = results.length
                ? el(
                    'ul',
                    { className: 'dragon-zap-affiliate-single-course-block__results' },
                    results.map(function (course) {
                        return el(
                            'li',
                            { key: course.id || course.title || Math.random(), className: 'dragon-zap-affiliate-single-course-block__results-item' },
                            el(
                                Button,
                                {
                                    isSecondary: true,
                                    onClick: function () {
                                        handleCourseSelect(course);
                                    },
                                },
                                el('span', { className: 'dragon-zap-affiliate-single-course-block__results-title' }, course.title || ''),
                                formatCoursePrice(course) !== ''
                                    ? el('span', { className: 'dragon-zap-affiliate-single-course-block__results-price' }, formatCoursePrice(course))
                                    : null
                            )
                        );
                    })
                )
                : null;

            var searchingIndicator = isSearching
                ? el('div', { className: 'dragon-zap-affiliate-single-course-block__status' },
                    el(Spinner, {}),
                    el('span', null, getString('searching', __('Searching…', 'dragon-zap-affiliate')))
                )
                : null;

            var errorNotice = errorMessage && ! isSearching
                ? el('div', { className: 'dragon-zap-affiliate-single-course-block__notice' }, errorMessage)
                : null;

            var selectionSummary = courseSelected
                ? el(
                    'div',
                    { className: 'dragon-zap-affiliate-single-course-block__selection' },
                    el('p', { className: 'dragon-zap-affiliate-single-course-block__selection-title' }, attributes.courseTitle || ''),
                    attributes.courseUrl
                        ? el(
                            'p',
                            null,
                            el(
                                'a',
                                {
                                    href: attributes.courseUrl,
                                    target: '_blank',
                                    rel: 'noopener noreferrer',
                                },
                                getString('viewCourse', __('View course', 'dragon-zap-affiliate'))
                            )
                        )
                        : null,
                    el(
                        Button,
                        {
                            isLink: true,
                            onClick: clearSelection,
                        },
                        getString('clearSelection', __('Remove selection', 'dragon-zap-affiliate'))
                    )
                )
                : el(
                    'p',
                    { className: 'dragon-zap-affiliate-single-course-block__notice' },
                    getString('selectCourse', __('Select a course to display.', 'dragon-zap-affiliate'))
                );

            var defaultHeadingText = getString('defaultHeading', __('Featured Course', 'dragon-zap-affiliate'));
            var headingValue = typeof attributes.title === 'string' ? attributes.title : '';
            var headingHelpText = __('Leave empty to hide the heading, or toggle it off below.', 'dragon-zap-affiliate');
            var inlineHeadingControls = components && (components.TextControl || components.ToggleControl)
                ? el(
                    'div',
                    { className: 'dragon-zap-affiliate-single-course-block__heading-controls' },
                    el(TextControl, {
                        label: __('Heading', 'dragon-zap-affiliate'),
                        value: headingValue,
                        placeholder: defaultHeadingText,
                        help: headingHelpText,
                        onChange: function (value) {
                            setAttributes({ title: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Show heading', 'dragon-zap-affiliate'),
                        checked: attributes.showTitle !== false,
                        onChange: function (value) {
                            setAttributes({ showTitle: value });
                        },
                    })
                )
                : null;

            var controls = el(
                Fragment,
                {},
                el(
                    PanelBody,
                    { title: __('Display Settings', 'dragon-zap-affiliate'), initialOpen: true },
                    el(TextControl, {
                        label: __('Heading', 'dragon-zap-affiliate'),
                        value: headingValue,
                        placeholder: defaultHeadingText,
                        help: headingHelpText,
                        onChange: function (value) {
                            setAttributes({ title: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Show heading', 'dragon-zap-affiliate'),
                        checked: attributes.showTitle !== false,
                        onChange: function (value) {
                            setAttributes({ showTitle: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Show course image', 'dragon-zap-affiliate'),
                        checked: attributes.showImages !== false,
                        onChange: function (value) {
                            setAttributes({ showImages: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Show description', 'dragon-zap-affiliate'),
                        checked: attributes.showDescription !== false,
                        onChange: function (value) {
                            setAttributes({ showDescription: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Show price', 'dragon-zap-affiliate'),
                        checked: attributes.showPrice !== false,
                        onChange: function (value) {
                            setAttributes({ showPrice: value });
                        },
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Style Settings', 'dragon-zap-affiliate'), initialOpen: false },
                    PanelColorSettings
                        ? el(PanelColorSettings, {
                            title: __('Color settings', 'dragon-zap-affiliate'),
                            colorSettings: [
                                {
                                    value: attributes.backgroundColor,
                                    onChange: function (value) {
                                        setAttributes({ backgroundColor: value || '' });
                                    },
                                    label: __('Background color', 'dragon-zap-affiliate'),
                                },
                                {
                                    value: attributes.textColor,
                                    onChange: function (value) {
                                        setAttributes({ textColor: value || '' });
                                    },
                                    label: __('Text color', 'dragon-zap-affiliate'),
                                },
                                {
                                    value: attributes.accentColor,
                                    onChange: function (value) {
                                        setAttributes({ accentColor: value || '' });
                                    },
                                    label: __('Link & accent color', 'dragon-zap-affiliate'),
                                },
                                {
                                    value: attributes.borderColor,
                                    onChange: function (value) {
                                        setAttributes({ borderColor: value || '' });
                                    },
                                    label: __('Border color', 'dragon-zap-affiliate'),
                                },
                            ],
                        })
                        : [
                            el(TextControl, {
                                key: 'backgroundColor',
                                label: __('Background color', 'dragon-zap-affiliate'),
                                value: attributes.backgroundColor,
                                onChange: function (value) {
                                    setAttributes({ backgroundColor: value });
                                },
                            }),
                            el(TextControl, {
                                key: 'textColor',
                                label: __('Text color', 'dragon-zap-affiliate'),
                                value: attributes.textColor,
                                onChange: function (value) {
                                    setAttributes({ textColor: value });
                                },
                            }),
                            el(TextControl, {
                                key: 'accentColor',
                                label: __('Link & accent color', 'dragon-zap-affiliate'),
                                value: attributes.accentColor,
                                onChange: function (value) {
                                    setAttributes({ accentColor: value });
                                },
                            }),
                            el(TextControl, {
                                key: 'borderColor',
                                label: __('Border color', 'dragon-zap-affiliate'),
                                value: attributes.borderColor,
                                onChange: function (value) {
                                    setAttributes({ borderColor: value });
                                },
                            }),
                        ],
                    el(TextControl, {
                        label: __('Additional CSS classes', 'dragon-zap-affiliate'),
                        value: attributes.customClass,
                        onChange: function (value) {
                            setAttributes({ customClass: value });
                        },
                    })
                )
            );

            var mainContentChildren = [
                searchInput,
                searchingIndicator,
                errorNotice,
                resultList,
                selectionSummary,
            ];

            if (inlineHeadingControls) {
                mainContentChildren.push(inlineHeadingControls);
            }

            if (isLoadingCourse) {
                mainContentChildren.push(
                    el(
                        'div',
                        { className: 'dragon-zap-affiliate-single-course-block__status' },
                        el(Spinner, {}),
                        el('span', null, __('Loading course details…', 'dragon-zap-affiliate'))
                    )
                );
            }

            if (preview && courseSelected) {
                mainContentChildren.push(preview);
            }

            return [
                el(InspectorControls, { key: 'controls' }, controls),
                el(
                    'div',
                    Object.assign({}, blockProps, { key: 'content', className: (blockProps.className || '') + ' dragon-zap-affiliate-single-course-block' }),
                    mainContentChildren
                ),
            ];
        },
        save: function () {
            return null;
        },
    });
})(window.wp || {});
