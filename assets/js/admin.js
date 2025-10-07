(function ($) {
    'use strict';

    function setResult(message, type) {
        var $result = $('#dragon-zap-affiliate-test-result');
        $result.removeClass('notice notice-success notice-error notice-warning');

        if (!message) {
            $result.empty();
            return;
        }

        var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        $result.addClass('notice ' + cssClass);
        $result.text(message);
    }

    $(function () {
        var $button = $('#dragon-zap-affiliate-test');
        var $apiField = $('#dragon_zap_affiliate_api_key');

        if (!$button.length) {
            return;
        }

        var originalText = dragonZapAffiliate.buttonLabel || $button.text();

        $button.on('click', function (event) {
            event.preventDefault();

            setResult('', '');
            $button.prop('disabled', true);
            $button.text(dragonZapAffiliate.testingText || 'Testing...');

            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'dza_test_connection',
                    nonce: dragonZapAffiliate.nonce,
                    api_key: $apiField.val()
                }
            }).done(function (response) {
                if (response.success) {
                    setResult(response.data.message || dragonZapAffiliate.testSuccessMessage, 'success');
                } else {
                    var message = response.data && response.data.message ? response.data.message : dragonZapAffiliate.testErrorMessage;
                    setResult(message, 'error');
                }
            }).fail(function () {
                setResult(dragonZapAffiliate.testErrorMessage, 'error');
            }).always(function () {
                $button.prop('disabled', false);
                $button.text(originalText);
            });
        });
    });
})(jQuery);
