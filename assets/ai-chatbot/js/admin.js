/**
 * WP AIgent — Admin JavaScript
 * Handles tab switching, API URL auto-fill, JSON Schema builder,
 * notification rules builder, and schema preview for the chatbot meta box.
 */
(function($) {
    'use strict';

    var config = window.aiChatbotAdmin || {};
    var schemaIdx = $('#js-schema-fields .js-schema-row').length;
    var notifyGroupIdx = $('#js-notify-rules-fields .ai-chatbot-rule-group').length;
    var captureGroupIdx = $('#js-capture-rules-fields .ai-chatbot-rule-group').length;
    $(document).ready(function() {
        // ===== Tab switching =====
        $('.ai-chatbot-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.ai-chatbot-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.ai-chatbot-tab-panel').removeClass('active');
            $('.ai-chatbot-tab-panel[data-tab="' + tab + '"]').addClass('active');
        });

        // ===== JSON Schema Builder =====
        var $schemaContainer = $('#js-schema-fields');
        var $template = $('#js-schema-row-tpl');

        // Add new field row
        $('.js-schema-add-row').on('click', function() {
            var html = $template.html().replace(/__IDX__/g, schemaIdx);
            var $row = $(html);
            $schemaContainer.append($row);
            schemaIdx++;
        });

        // Remove field row (event delegation)
        $schemaContainer.on('click', '.js-schema-remove-row', function() {
            $(this).closest('.js-schema-row').remove();
        });

        // Toggle enum values field when type changes (event delegation)
        $schemaContainer.on('change', '.js-schema-field-type select', function() {
            var $row = $(this).closest('.js-schema-row');
            var type = $(this).val();
            $row.find('.js-schema-dependent').each(function() {
                if ($(this).data('dep-type') === type) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // ===== Notification Rules Builder (grouped: OR between groups, AND within) =====
        var $notifyContainer = $('#js-notify-rules-fields');
        var $notifyGroupTpl = $('#js-notify-group-tpl');
        var $notifyCondTpl = $('#js-notify-condition-tpl');

        // Add a new rule group
        $('.js-notify-add-group').on('click', function() {
            var html = $notifyGroupTpl.html().replace(/__NGIDX__/g, notifyGroupIdx);
            var $group = $(html);
            $notifyContainer.append($group);
            notifyGroupIdx++;
        });

        // Remove a rule group
        $notifyContainer.on('click', '.js-notify-remove-group', function() {
            $(this).closest('.ai-chatbot-rule-group').remove();
        });

        // Add a condition to a specific group
        $notifyContainer.on('click', '.js-notify-add-condition', function() {
            var $group = $(this).closest('.ai-chatbot-rule-group');
            var gidx = $group.data('group-index');
            var $conditions = $group.find('.ai-chatbot-rule-group-conditions');
            var cidx = $conditions.children().length;
            var html = $notifyCondTpl.html()
                .replace(/__NGIDX__/g, gidx)
                .replace(/__NCIDX__/g, cidx);
            $conditions.append(html);
        });

        // Remove a condition from a group
        $notifyContainer.on('click', '.js-notify-remove-condition', function() {
            $(this).closest('.ai-chatbot-condition-row').remove();
        });

        // ===== Lead Capture Rules Builder (grouped: OR between groups, AND within) =====
        var $captureContainer = $('#js-capture-rules-fields');
        var $captureGroupTpl = $('#js-capture-group-tpl');
        var $captureCondTpl = $('#js-capture-condition-tpl');

        // Add a new rule group
        $(document).on('click', '.js-capture-add-group', function() {
            var html = $captureGroupTpl.html().replace(/__GIDX__/g, captureGroupIdx);
            var $group = $(html);
            $captureContainer.append($group);
            captureGroupIdx++;
        });

        // Remove a rule group
        $captureContainer.on('click', '.js-capture-remove-group', function() {
            $(this).closest('.ai-chatbot-rule-group').remove();
        });

        // Add a condition to a specific group
        $captureContainer.on('click', '.js-capture-add-condition', function() {
            var $group = $(this).closest('.ai-chatbot-rule-group');
            var gidx = $group.data('group-index');
            var $conditions = $group.find('.ai-chatbot-rule-group-conditions');
            var cidx = $conditions.children().length;
            var html = $captureCondTpl.html()
                .replace(/__GIDX__/g, gidx)
                .replace(/__CIDX__/g, cidx);
            $conditions.append(html);
        });

        // Remove a condition from a group
        $captureContainer.on('click', '.js-capture-remove-condition', function() {
            $(this).closest('.ai-chatbot-condition-row').remove();
        });

        // ===== Lead Capture Form Fields Builder =====
        var $leadFieldsContainer = $('#js-lead-fields');
        var $leadFieldsTpl = $('#js-lead-field-tpl');

        $(document).on('click', '.js-lead-field-add', function() {
            var lidx = $leadFieldsContainer.children().length;
            var html = $leadFieldsTpl.html().replace(/__LIDX__/g, lidx);
            $leadFieldsContainer.append(html);
        });

        $leadFieldsContainer.on('click', '.js-lead-field-remove', function() {
            $(this).closest('.js-lead-field-row').remove();
        });

        // ===== Model Selects: Read Saved Provider Models =====
        var modelControls = [
            {
                provider: $('#chatbot_primary_api_provider_id'),
                select: $('#chatbot_primary_api_model')
            },
            {
                provider: $('#chatbot_fallback_api_provider_id'),
                select: $('#chatbot_fallback_api_model')
            },
            {
                provider: $('#chatbot_knowledge_router_provider_id'),
                select: $('#chatbot_knowledge_router_model')
            },
            {
                provider: $('#knowledge_metadata_provider_id'),
                select: $('#knowledge_metadata_model')
            }
        ];

        function populateModelSelect(control) {
            var providerId = control.provider.val();
            if (control.inheritProvider && control.inheritValues && control.inheritValues.indexOf(String(providerId)) !== -1) {
                providerId = control.inheritProvider.val();
            }
            var models = (config.providerModels && config.providerModels[providerId]) || [];
            var currentModel = control.select.val();

            control.select.find('option:not([value=""])').remove();
            $.each(models, function(_, model) {
                control.select.append($('<option>').val(model).text(model));
            });

            if (models.indexOf(currentModel) !== -1) {
                control.select.val(currentModel);
            } else {
                control.select.val('');
            }
        }

        $.each(modelControls, function(_, control) {
            control.provider.on('change', function() {
                populateModelSelect(control);
            });
            if (control.inheritProvider) {
                control.inheritProvider.on('change', function() {
                    if (control.inheritValues.indexOf(String(control.provider.val())) !== -1) populateModelSelect(control);
                });
            }
        });

        // ===== Inactivity Timeout: Enable/Disable =====
        var $inactivityCheckbox = $('#chatbot_notify_inactivity_enabled');
        var $inactivityPanel = $('#js-notify-inactivity-settings');
        $inactivityCheckbox.on('change', function() {
            if ($(this).is(':checked')) {
                $inactivityPanel.css({ opacity: 1, pointerEvents: '' });
                $inactivityPanel.find('input, select, button').prop('disabled', false);
            } else {
                $inactivityPanel.css({ opacity: 0.5, pointerEvents: 'none' });
                $inactivityPanel.find('input, select, button').prop('disabled', true);
            }
        });
    });
})(jQuery);
