(function () {
    'use strict';

    var config = window.wpAIgentFormAnalysis || {};
    var form = document.getElementById('wp-aigent-form-analysis-run');
    var provider = document.getElementById('wp-aigent-analysis-provider');
    var model = document.getElementById('wp-aigent-analysis-model');
    var progress = document.getElementById('wp-aigent-analysis-progress');
    var button = document.getElementById('wp-aigent-analysis-start');

    function fillModels() {
        if (!provider || !model) return;
        var selected = model.dataset.selected || '';
        var models = (config.providerModels || {})[provider.value] || [];
        model.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = config.i18n.chooseModel;
        model.appendChild(placeholder);
        models.forEach(function (name) {
            var option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            option.selected = name === selected;
            model.appendChild(option);
        });
        model.dataset.selected = '';
    }

    function request(action, data) {
        var body = new URLSearchParams(Object.assign({ action: action, nonce: config.nonce }, data));
        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) { return response.json(); }).then(function (payload) {
            if (!payload.success) throw new Error((payload.data && payload.data.message) || config.i18n.failed);
            return payload.data;
        });
    }

    function showJob(job) {
        if (!job || !progress) return;
        progress.hidden = false;
        progress.className = 'notice notice-info inline';
        progress.textContent = config.i18n.progress
            .replace('%1$d', job.inspected_count || 0)
            .replace('%2$d', job.succeeded_count || 0)
            .replace('%3$d', job.skipped_count || 0)
            .replace('%4$d', job.failed_count || 0);
    }

    function run(jobId) {
        request('wp_aigent_form_analysis_batch', { job_id: jobId }).then(function (data) {
            showJob(data.job);
            if (data.done) {
                progress.className = 'notice notice-success inline';
                progress.textContent += ' ' + config.i18n.complete;
                button.disabled = false;
                window.setTimeout(function () { window.location.reload(); }, 900);
                return;
            }
            run(jobId);
        }).catch(function (error) {
            progress.hidden = false;
            progress.className = 'notice notice-error inline';
            progress.textContent = error.message;
            button.disabled = false;
        });
    }

    if (provider && model) {
        provider.addEventListener('change', function () { model.dataset.selected = ''; fillModels(); });
        fillModels();
    }

    if (form) form.addEventListener('submit', function (event) {
        event.preventDefault();
        button.disabled = true;
        progress.hidden = false;
        progress.className = 'notice notice-info inline';
        progress.textContent = config.i18n.starting;
        request('wp_aigent_form_analysis_create', {
            date_from: form.elements.date_from.value,
            date_to: form.elements.date_to.value
        }).then(function (data) { run(data.job_id); }).catch(function (error) {
            progress.className = 'notice notice-error inline';
            progress.textContent = error.message;
            button.disabled = false;
        });
    });
}());
