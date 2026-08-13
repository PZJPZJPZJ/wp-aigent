(function () {
    'use strict';

    var config = window.wpAIgentFormAnalysis || {};
    var form = document.getElementById('wp-aigent-submissions-filter');
    var provider = document.getElementById('wp-aigent-analysis-provider');
    var model = document.getElementById('wp-aigent-analysis-model');
    var progress = document.getElementById('wp-aigent-analysis-progress');
    var buttons = form ? Array.prototype.slice.call(form.querySelectorAll('[data-analysis-mode]')) : [];
    var progressTrack = progress && progress.querySelector('.wp-aigent-progress-track');
    var progressBar = progressTrack && progressTrack.querySelector('span');
    var progressLabel = progress && progress.querySelector('.wp-aigent-progress-label');
    var analysisRunning = false;

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

    function setButtonsDisabled(disabled) {
        analysisRunning = disabled;
        buttons.forEach(function (button) { button.disabled = disabled; });
    }

    function showJob(job) {
        if (!job || !progress) return;
        var total = Number(job.total_count || 0);
        var processed = Number(job.inspected_count || 0);
        var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 100;
        progress.hidden = false;
        progress.className = 'wp-aigent-analysis-progress';
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(percent));
        if (progressLabel) progressLabel.textContent = config.i18n.progress
            .replace('%1$d', processed)
            .replace('%2$d', total)
            .replace('%3$d', percent)
            .replace('%4$d', job.succeeded_count || 0)
            .replace('%5$d', job.skipped_count || 0)
            .replace('%6$d', job.failed_count || 0);
    }

    function run(jobId) {
        request('wp_aigent_form_analysis_batch', { job_id: jobId }).then(function (data) {
            showJob(data.job);
            if (data.done) {
                if (progressBar) progressBar.style.width = '100%';
                if (progressTrack) progressTrack.setAttribute('aria-valuenow', '100');
                if (progressLabel) progressLabel.textContent += ' ' + config.i18n.complete;
                setButtonsDisabled(false);
                window.setTimeout(function () { window.location.reload(); }, 900);
                return;
            }
            run(jobId);
        }).catch(function (error) {
            progress.hidden = false;
            progress.className = 'wp-aigent-analysis-progress notice notice-error inline';
            if (progressLabel) progressLabel.textContent = error.message;
            setButtonsDisabled(false);
        });
    }

    if (provider && model) {
        provider.addEventListener('change', function () { model.dataset.selected = ''; fillModels(); });
        fillModels();
    }

    if (form) form.addEventListener('submit', function (event) {
        if (analysisRunning) event.preventDefault();
    });

    buttons.forEach(function (button) { button.addEventListener('click', function () {
        var analysisMode = button.dataset.analysisMode === 'overwrite' ? 'overwrite' : 'update';
        var values = new FormData(form);
        setButtonsDisabled(true);
        progress.hidden = false;
        progress.className = 'wp-aigent-analysis-progress';
        if (progressBar) progressBar.style.width = '0%';
        if (progressLabel) progressLabel.textContent = analysisMode === 'overwrite' ? config.i18n.startingOverwrite : config.i18n.startingUpdate;
        request('wp_aigent_form_analysis_create', {
            date_from: values.get('date_from') || '',
            date_to: values.get('date_to') || '',
            search: values.get('s') || '',
            form: values.get('form') || '',
            page_url: values.get('page_url') || '',
            is_spam: values.get('is_spam') || '',
            intent: values.get('intent') || '',
            analysis_status: values.get('analysis_status') || '',
            analysis_mode: analysisMode
        }).then(function (data) {
            showJob(data.job);
            if (progressLabel) progressLabel.textContent = config.i18n.counted.replace('%d', data.job.total_count || 0);
            run(data.job_id);
        }).catch(function (error) {
            progress.className = 'wp-aigent-analysis-progress notice notice-error inline';
            if (progressLabel) progressLabel.textContent = error.message;
            setButtonsDisabled(false);
        });
    }); });
}());
