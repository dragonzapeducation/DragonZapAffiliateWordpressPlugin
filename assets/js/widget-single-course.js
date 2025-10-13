(function ($) {
    var settings = window.dragonZapAffiliateSingleCourse || {};
    var strings = settings.strings || {};
    var ajaxUrl = settings.ajaxUrl || '';
    var nonce = settings.nonce || '';

    function getString(key, fallback) {
        if (strings && typeof strings[key] === 'string') {
            return strings[key];
        }

        return fallback;
    }

    function formatPrice(course) {
        if (! course || course.price === null || typeof course.price === 'undefined') {
            return '';
        }

        var price = Number(course.price);

        if (! isFinite(price)) {
            return '';
        }

        var result = price.toFixed(2);
        var currency = course.currency ? String(course.currency) : '';

        return currency ? currency + ' ' + result : result;
    }

    function renderSelected($container, course) {
        var $selected = $container.find('.dragon-zap-affiliate-course-selected');
        var $clear = $container.find('.dragon-zap-affiliate-course-clear');

        if (! course) {
            $selected.html(
                $('<p />', {
                    'class': 'description',
                    text: getString('emptySelected', 'No course selected yet. Use the search above to choose one.')
                })
            );
            $clear.hide();
            return;
        }

        var $item = $('<div />', {'class': 'dragon-zap-affiliate-course-selected__item'});

        if (course.image) {
            $('<img />', {
                'class': 'dragon-zap-affiliate-course-selected__image',
                src: course.image,
                alt: course.title || ''
            }).appendTo($item);
        }

        var $content = $('<div />', {'class': 'dragon-zap-affiliate-course-selected__content'});
        $('<strong />', {
            'class': 'dragon-zap-affiliate-course-selected__title',
            text: course.title || ''
        }).appendTo($content);

        var priceText = formatPrice(course);

        if (priceText !== '') {
            $('<span />', {
                'class': 'dragon-zap-affiliate-course-selected__price',
                text: priceText
            }).appendTo($content);
        }

        if (course.url) {
            $('<div />').append(
                $('<a />', {
                    href: course.url,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    text: getString('viewCourse', 'View course')
                })
            ).appendTo($content);
        }

        $item.append($content);
        $selected.empty().append($item);
        $clear.show();
    }

    function renderResults($results, courses) {
        $results.empty();

        if (! courses.length) {
            $('<li />', {
                'class': 'dragon-zap-affiliate-course-results__item dragon-zap-affiliate-course-results__item--empty',
                text: getString('noResults', 'No courses found. Try another search term.')
            }).appendTo($results);
            return;
        }

        courses.forEach(function (course) {
            var $item = $('<li />', {'class': 'dragon-zap-affiliate-course-results__item'});
            var $button = $('<button />', {
                type: 'button',
                'class': 'dragon-zap-affiliate-course-results__button'
            }).data('course', course);

            $('<span />', {
                'class': 'dragon-zap-affiliate-course-results__title',
                text: course.title || ''
            }).appendTo($button);

            var priceText = formatPrice(course);

            if (priceText !== '') {
                $('<span />', {
                    'class': 'dragon-zap-affiliate-course-results__price',
                    text: priceText
                }).appendTo($button);
            }

            if (course.description) {
                $('<span />', {
                    'class': 'dragon-zap-affiliate-course-results__description',
                    text: course.description
                }).appendTo($button);
            }

            $item.append($button);
            $results.append($item);
        });
    }

    function showStatus($results, message) {
        $results
            .empty()
            .append(
                $('<li />', {
                    'class': 'dragon-zap-affiliate-course-results__item dragon-zap-affiliate-course-results__item--status',
                    text: message
                })
            );
    }

    function initWidget($widget) {
        if ($widget.data('dragonZapSingleCourseInit')) {
            return;
        }

        $widget.data('dragonZapSingleCourseInit', true);

        var $search = $widget.find('.dragon-zap-affiliate-course-search');
        var $results = $widget.find('.dragon-zap-affiliate-course-results');
        var $hidden = $widget.find('.dragon-zap-affiliate-course-id');
        var $clear = $widget.find('.dragon-zap-affiliate-course-clear');
        var activeRequest = null;

        if ($hidden.val()) {
            $clear.show();
        } else {
            $clear.hide();
        }

        $search.on('input', function () {
            var query = $.trim($search.val());

            if (activeRequest && typeof activeRequest.abort === 'function') {
                activeRequest.abort();
                activeRequest = null;
            }

            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            if (! ajaxUrl || ! nonce) {
                return;
            }

            $results.show();
            showStatus($results, getString('searching', 'Searching...'));

            activeRequest = $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'dza_search_courses',
                    nonce: nonce,
                    search: query
                }
            }).done(function (response) {
                if (! response || ! response.success || ! response.data || ! $.isArray(response.data.courses)) {
                    showStatus($results, getString('error', 'Something went wrong. Please try again.'));
                    return;
                }

                renderResults($results, response.data.courses);
            }).fail(function () {
                showStatus($results, getString('error', 'Something went wrong. Please try again.'));
            });
        });

        $results.on('click', '.dragon-zap-affiliate-course-results__button', function (event) {
            event.preventDefault();

            var course = $(this).data('course');

            if (! course || ! course.id) {
                return;
            }

            $hidden.val(course.id);
            renderSelected($widget, course);
            $results.hide().empty();
            $search.val('');
        });

        $clear.on('click', function (event) {
            event.preventDefault();
            $hidden.val('');
            renderSelected($widget, null);
        });
    }

    function initAll($root) {
        $root.find('.dragon-zap-affiliate-single-course-widget').each(function () {
            initWidget($(this));
        });
    }

    $(document).on('widget-added widget-updated', function (event, widget) {
        initAll($(widget));
    });

    $(document).ready(function () {
        initAll($(document));
    });
})(jQuery);

