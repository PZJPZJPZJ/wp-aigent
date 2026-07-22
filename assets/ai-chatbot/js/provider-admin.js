(function($) {
    'use strict';

    $(function() {
        var config = window.aiProviderAdmin || {};
        var $platform = $('#provider_platform');
        var $baseUrl = $('#provider_api_base_url');
        var $fetchButton = $('#js-provider-fetch-models');
        var $editButton = $('#js-provider-edit-models');
        var $status = $('#js-provider-fetch-status');
        var $list = $('#js-provider-model-list');
        var $inputWrap = $('#js-provider-model-input-wrap');
        var $input = $('#js-provider-model-input');
        var defaults = {
            openai: 'https://api.openai.com/v1',
            anthropic: 'https://api.anthropic.com/v1'
        };

        $platform.on('change', function() {
            var next = defaults[$(this).val()];
            var current = $baseUrl.val().replace(/\/+$/, '');
            var isDefault = Object.keys(defaults).some(function(key) {
                return current === defaults[key].replace(/\/+$/, '');
            });
            if (next && (current === '' || isDefault)) {
                $baseUrl.val(next);
            }
            $fetchButton.prop('disabled', $platform.val() === 'anthropic');
        });

        function createModelChip(model) {
            var $chip = $('<span>').addClass('ai-provider-model-chip').attr('data-model', model);
            $('<code>').text(model).appendTo($chip);
            $('<button>')
                .attr({ type: 'button', 'aria-label': config.i18n.deleteModel, hidden: true })
                .addClass('ai-provider-model-delete')
                .text('×')
                .appendTo($chip);
            return $chip;
        }

        function getModels() {
            return $list.find('.ai-provider-model-chip').map(function() {
                return $(this).data('model');
            }).get();
        }

        function replaceModels(models) {
            $list.find('.ai-provider-model-chip').remove();
            $.each(models, function(_, model) {
                createModelChip(model).insertBefore($inputWrap);
            });
        }

        function saveModels() {
            return $.post(ajaxurl, {
                action: 'ai_provider_save_models',
                provider_id: config.providerId,
                models: getModels(),
                _ajax_nonce: config.manageModelsNonce
            }).done(function(response) {
                if (!response.success) {
                    $status.text((response.data && response.data.message) || config.i18n.saveFailed);
                }
            }).fail(function() {
                $status.text(config.i18n.saveFailed);
            });
        }

        function closeEditor() {
            $inputWrap.prop('hidden', true);
            $input.val('');
            setEditing(false);
            $editButton.text(config.i18n.editModels);
        }

        function setEditing(isEditing) {
            $list.toggleClass('is-editing', isEditing);
            $list.find('.ai-provider-model-delete').prop('hidden', !isEditing);
        }

        function addInputModel() {
            var model = $.trim($input.val());
            if (model && getModels().indexOf(model) === -1) {
                createModelChip(model).insertBefore($inputWrap);
                setEditing(true);
                saveModels();
            }
            closeEditor();
        }

        $editButton.on('click', function() {
            if (!config.providerId) {
                return;
            }
            if ($inputWrap.prop('hidden')) {
                $inputWrap.prop('hidden', false);
                setEditing(true);
                $input.focus();
                $editButton.text(config.i18n.done);
            } else {
                addInputModel();
            }
        });

        $input.on('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addInputModel();
            } else if (event.key === 'Escape') {
                closeEditor();
            }
        });

        $list.on('click', '.ai-provider-model-delete', function() {
            $(this).closest('.ai-provider-model-chip').remove();
            saveModels();
        });

        $fetchButton.on('click', function() {
            if (!config.providerId || $fetchButton.prop('disabled')) {
                return;
            }

            $fetchButton.prop('disabled', true).text(config.i18n.fetching);
            $status.text('');

            $.post(ajaxurl, {
                action: 'ai_chatbot_fetch_models',
                provider_id: config.providerId,
                _ajax_nonce: config.fetchModelsNonce
            }).done(function(response) {
                if (!response.success || !response.data || !response.data.models) {
                    $status.text((response.data && response.data.message) || config.i18n.fetchFailed);
                    return;
                }

                replaceModels(response.data.models);
                closeEditor();
                $status.text(config.i18n.modelsFound.replace('%d', response.data.models.length));
            }).fail(function() {
                $status.text(config.i18n.fetchFailed);
            }).always(function() {
                $fetchButton.prop('disabled', $platform.val() === 'anthropic').text(config.i18n.fetchModels);
            });
        });
    });
})(jQuery);
