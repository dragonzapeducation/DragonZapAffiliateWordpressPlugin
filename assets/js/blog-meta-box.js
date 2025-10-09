(function ($) {
    'use strict';

    var settings = window.dragonZapAffiliateBlogMeta || {};
    var createOptionValue = settings.createOptionValue || '';
    var ajaxUrl = settings.ajaxUrl || window.ajaxurl || '';

    function getString(key, fallback) {
        var strings = settings.strings || {};
        var value = strings[key];

        if (typeof value === 'string' && value.length > 0) {
            return value;
        }

        return fallback;
    }

    function formatProfileLabel(profile) {
        var name = (profile && profile.name) ? String(profile.name) : '';
        var identifier = (profile && profile.identifier) ? String(profile.identifier) : '';

        if (name && identifier) {
            return name + ' (' + identifier + ')';
        }

        if (name) {
            return name;
        }

        if (identifier) {
            return identifier;
        }

        return '';
    }

    function getNoticeContainer($metaBox) {
        var $notice = $metaBox.find('.dragon-zap-affiliate-blog-meta__notice');

        if (!$notice.length) {
            $notice = $('<div>')
                .addClass('dragon-zap-affiliate-blog-meta__notice notice')
                .attr('aria-live', 'polite');

            $metaBox.prepend($notice);
        }

        return $notice;
    }

    function showNotice($metaBox, type, message) {
        var $notice = getNoticeContainer($metaBox);
        var classes = 'dragon-zap-affiliate-blog-meta__notice notice ';

        $notice.removeClass();

        if (type === 'success') {
            classes += 'notice-success';
        } else {
            classes += 'notice-error';
        }

        $notice.addClass(classes).empty().append($('<p>').text(message));
    }

    function createField(id, labelText, type, placeholder) {
        var $fieldWrapper = $('<div>').addClass('dragon-zap-affiliate-modal__field');
        var $label = $('<label>').attr('for', id).text(labelText);
        var $input = $('<input>')
            .attr({
                id: id,
                type: type,
                required: 'required',
                autocomplete: 'off',
            })
            .attr('placeholder', placeholder || '');

        $fieldWrapper.append($label, $input);

        return {
            wrapper: $fieldWrapper,
            input: $input,
        };
    }

    function openCreateModal($select) {
        var $metaBox = $select.closest('.dragon-zap-affiliate-blog-meta');
        var previousValue = $select.data('dzaLastValue') || '';

        if ($select.data('dzaModalOpen')) {
            return;
        }

        $select.data('dzaModalOpen', true);

        var overlayId = 'dragon-zap-affiliate-modal-' + Date.now();
        var nameFieldId = overlayId + '-name';
        var identifierFieldId = overlayId + '-identifier';

        var $overlay = $('<div>')
            .addClass('dragon-zap-affiliate-modal-overlay')
            .attr('role', 'presentation');

        var $dialog = $('<div>')
            .addClass('dragon-zap-affiliate-modal')
            .attr({
                role: 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': overlayId + '-title',
            });

        var $title = $('<h2>')
            .attr('id', overlayId + '-title')
            .addClass('dragon-zap-affiliate-modal__title')
            .text(getString('modalTitle', 'Create blog profile'));

        var nameField = createField(
            nameFieldId,
            getString('nameLabel', 'Profile name'),
            'text',
            getString('namePlaceholder', '')
        );

        var identifierField = createField(
            identifierFieldId,
            getString('identifierLabel', 'Profile identifier'),
            'text',
            getString('identifierPlaceholder', '')
        );

        var $error = $('<p>')
            .addClass('dragon-zap-affiliate-modal__error')
            .attr('role', 'alert')
            .hide();

        var $actions = $('<div>').addClass('dragon-zap-affiliate-modal__actions');
        var $cancel = $('<button>')
            .attr('type', 'button')
            .addClass('button button-secondary')
            .text(getString('cancelButton', 'Cancel'));
        var $submit = $('<button>')
            .attr('type', 'submit')
            .addClass('button button-primary')
            .text(getString('createButton', 'Create profile'));

        $actions.append($cancel, $submit);

        var $form = $('<form>').addClass('dragon-zap-affiliate-modal__form');
        $form.append(nameField.wrapper, identifierField.wrapper, $error, $actions);

        $dialog.append($title, $form);
        $overlay.append($dialog);

        var $previouslyFocused = $(document.activeElement);

        function closeModal() {
            $(document).off('keydown.dragonZapAffiliateModal');
            $overlay.remove();
            $select.data('dzaModalOpen', false);

            if ($previouslyFocused && $previouslyFocused.length) {
                $previouslyFocused.trigger('focus');
            } else {
                $select.trigger('focus');
            }
        }

        function setSubmitting(isSubmitting) {
            nameField.input.prop('disabled', isSubmitting);
            identifierField.input.prop('disabled', isSubmitting);
            $submit.prop('disabled', isSubmitting);
            $cancel.prop('disabled', isSubmitting);

            if (isSubmitting) {
                $submit.text(getString('creatingText', 'Creating...'));
            } else {
                $submit.text(getString('createButton', 'Create profile'));
            }
        }

        function handleError(message) {
            var output = message || getString('errorDefault', 'Something went wrong. Please try again.');
            $error.text(output).show();
        }

        function ensureCreateOptionExists() {
            if (!createOptionValue) {
                return;
            }

            if (!$select.find('option[value="' + createOptionValue + '"]').length) {
                $select.append(
                    $('<option>')
                        .attr('value', createOptionValue)
                        .text(getString('createOptionLabel', 'Create new profile'))
                );
            }
        }

        function handleSuccess(data) {
            var payload = data && data.profile ? data.profile : null;

            if (!payload || typeof payload.id === 'undefined') {
                handleError(getString('errorDefault', 'Something went wrong. Please try again.'));
                setSubmitting(false);
                return;
            }

            var id = String(payload.id);
            var label = formatProfileLabel(payload);

            if (!label) {
                label = id;
            }

            var $existing = $select.find('option[value="' + id + '"]');
            var $createOption = createOptionValue ? $select.find('option[value="' + createOptionValue + '"]') : $();

            if ($existing.length) {
                $existing.text(label);
            } else if ($createOption.length) {
                $('<option>').attr('value', id).text(label).insertBefore($createOption);
            } else {
                $select.append($('<option>').attr('value', id).text(label));
            }

            ensureCreateOptionExists();

            $select.val(id);
            $select.data('dzaLastValue', id);
            $select.trigger('change');

            closeModal();
            showNotice($metaBox, 'success', (data && data.message) ? data.message : getString('successMessage', 'Blog profile created successfully.'));
        }

        $form.on('submit', function (event) {
            event.preventDefault();

            $error.hide().empty();

            var nameValue = nameField.input.val();
            var identifierValue = identifierField.input.val();

            if (!nameValue || !identifierValue) {
                handleError(getString('missingFields', 'Please enter both a name and an identifier.'));
                return;
            }

            if (!ajaxUrl) {
                handleError(getString('errorDefault', 'Something went wrong. Please try again.'));
                return;
            }

            setSubmitting(true);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'dza_create_blog_profile',
                    nonce: settings.nonce,
                    name: nameValue,
                    identifier: identifierValue,
                },
            }).done(function (response) {
                if (response && response.success) {
                    handleSuccess(response.data || {});
                } else {
                    var message = response && response.data && response.data.message ? response.data.message : '';
                    handleError(message);
                    setSubmitting(false);
                }
            }).fail(function (jqXHR) {
                var message = '';

                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data) {
                    message = jqXHR.responseJSON.data.message || '';
                }

                handleError(message);
                setSubmitting(false);
            });
        });

        $cancel.on('click', function () {
            $select.val(previousValue);
            $select.data('dzaLastValue', previousValue);
            closeModal();
        });

        $overlay.on('click', function (event) {
            if (event.target === $overlay.get(0)) {
                $select.val(previousValue);
                $select.data('dzaLastValue', previousValue);
                closeModal();
            }
        });

        $(document).on('keydown.dragonZapAffiliateModal', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                $select.val(previousValue);
                $select.data('dzaLastValue', previousValue);
                closeModal();
            }
        });

        $('body').append($overlay);
        nameField.input.trigger('focus');
    }

    function bindSelect($select) {
        $select.data('dzaLastValue', $select.val() || '');

        $select.on('change', function () {
            var value = $select.val();

            if (value === createOptionValue && createOptionValue) {
                var previousValue = $select.data('dzaLastValue') || '';
                $select.val(previousValue);
                openCreateModal($select);
                return;
            }

            $select.data('dzaLastValue', value || '');
        });
    }

    $(function () {
        if (!createOptionValue) {
            return;
        }

        $('.dragon-zap-affiliate-blog-meta').each(function () {
            var $metaBox = $(this);
            var $select = $metaBox.find('select[data-dza-blog-profile-select="1"]');

            if ($select.length) {
                bindSelect($select);
            }
        });
    });
})(jQuery);
