(function ($) {
    function initColorPicker($context) {
        $context.find('.dragon-zap-affiliate-color-field').each(function () {
            var $input = $(this);

            if (typeof $input.wpColorPicker !== 'function') {
                return;
            }

            if ($input.data('wpColorPicker')) {
                return;
            }

            $input.wpColorPicker({
                change: function () {
                    $input.trigger('change');
                },
                clear: function () {
                    $input.trigger('change');
                }
            });
        });
    }

    $(document).on('widget-added widget-updated', function (event, widget) {
        initColorPicker($(widget));
    });

    $(document).ready(function () {
        initColorPicker($(document));
    });
})(jQuery);
