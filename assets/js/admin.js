(function ($) {
    'use strict';

    var settings = window.dragonZapAffiliate || {};

    function getString(key, fallback) {
        var value = settings[key];

        if (typeof value === 'string' && value.length > 0) {
            return value;
        }

        return fallback;
    }

    function getResultContainer() {
        return $('#dragon-zap-affiliate-test-result');
    }

    function clearResult() {
        var $result = getResultContainer();
        $result.removeClass('notice notice-success notice-error notice-warning');
        $result.empty();
    }

    function createScopeBlock(title, items, emptyMessage, description) {
        var $block = $('<div>').addClass('dragon-zap-affiliate-scope-block');

        if (title) {
            $block.append($('<h3>').text(title));
        }

        if (Array.isArray(items) && items.length) {
            var $list = $('<ul>').addClass('dragon-zap-affiliate-scope-list');

            items.forEach(function (item) {
                $list.append($('<li>').text(item));
            });

            $block.append($list);
        } else if (emptyMessage) {
            $block.append($('<p>').addClass('description').text(emptyMessage));
        }

        if (description) {
            $block.append($('<p>').addClass('description').text(description));
        }

        return $block;
    }

    function createEndpointBlock(title, url, description) {
        var $block = $('<div>').addClass('dragon-zap-affiliate-scope-block');

        if (title) {
            $block.append($('<h3>').text(title));
        }

        if (url) {
            $block.append(
                $('<code>')
                    .addClass('dragon-zap-affiliate-endpoint')
                    .attr('aria-label', title || getString('endpointTitle', 'Affiliate test endpoint'))
                    .text(url)
            );
        }

        if (description) {
            $block.append($('<p>').addClass('description').text(description));
        }

        return $block;
    }

    function renderResult(type, message, blocks) {
        var $result = getResultContainer();

        clearResult();

        if (!message && (!Array.isArray(blocks) || !blocks.length)) {
            return;
        }

        var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        $result.addClass('notice ' + cssClass);

        if (message) {
            $result.append($('<p>').text(message));
        }

        if (Array.isArray(blocks) && blocks.length) {
            var $details = $('<div>').addClass('dragon-zap-affiliate-scope-details');

            blocks.forEach(function ($block) {
                $details.append($block);
            });

            $result.append($details);
        }
    }

    $(function () {
        var $button = $('#dragon-zap-affiliate-test');
        var $apiField = $('#dragon_zap_affiliate_api_key');

        if (!$button.length) {
            return;
        }

        var originalText = getString('buttonLabel', $button.text());

        $button.on('click', function (event) {
            event.preventDefault();

            clearResult();
            $button.prop('disabled', true);
            $button.text(getString('testingText', 'Testing...'));

            $.ajax({
                url: window.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'dza_test_connection',
                    nonce: settings.nonce,
                    api_key: $apiField.val()
                }
            }).done(function (response) {
                if (response && response.success) {
                    var apiResponse = response.data && response.data.response ? response.data.response : {};
                    var payload = apiResponse.data && typeof apiResponse.data === 'object' ? apiResponse.data : {};
                    var scopes = Array.isArray(payload.scopes) ? payload.scopes : [];
                    var restrictions = Array.isArray(payload.restrictions) ? payload.restrictions : [];

                    var blocks = [
                        createScopeBlock(
                            getString('scopesTitle', 'Authorized scopes'),
                            scopes,
                            getString('scopesEmpty', 'No scopes were returned for this API key.')
                        ),
                        createScopeBlock(
                            getString('restrictionsTitle', 'Restricted scopes'),
                            restrictions,
                            getString('restrictionsEmpty', 'No restricted scopes were reported for this API key.'),
                            getString('restrictionsHelp', '')
                        )
                    ];

                    var endpointUrl = getString('testEndpointUrl', '');

                    if (endpointUrl) {
                        blocks.push(
                            createEndpointBlock(
                                getString('endpointTitle', 'Affiliate test endpoint'),
                                endpointUrl,
                                getString('endpointDescription', '')
                            )
                        );
                    }

                    renderResult(
                        'success',
                        (response.data && response.data.message) || getString('testSuccessMessage', 'Connection successful!'),
                        blocks
                    );
                } else {
                    var message = response && response.data && response.data.message ? response.data.message : getString('testErrorMessage', 'Connection failed. Please check your API key and try again.');
                    renderResult('error', message);
                }
            }).fail(function () {
                renderResult('error', getString('testErrorMessage', 'Connection failed. Please check your API key and try again.'));
            }).always(function () {
                $button.prop('disabled', false);
                $button.text(originalText);
            });
        });
    });
})(jQuery);
