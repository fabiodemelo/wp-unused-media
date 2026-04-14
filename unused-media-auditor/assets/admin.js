function initUmaBulkForm(form) {
    if (form.dataset.umaBulkInitialized === 'true') {
        return;
    }

    form.dataset.umaBulkInitialized = 'true';

    var toggle = form.querySelector('.uma-toggle-all');
    var itemCheckboxes = Array.prototype.slice.call(form.querySelectorAll('input[name="attachment_ids[]"]'));
    var selectionSummary = form.querySelector('.uma-selection-summary');
    var applyButton = form.querySelector('.uma-apply-action');
    var actionSelect = form.querySelector('.uma-bulk-action');

    if (!toggle || !itemCheckboxes.length) {
        return;
    }

    var updateSelectionState = function () {
        var selectedCount = itemCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        if (selectionSummary) {
            selectionSummary.textContent = selectedCount + (selectedCount === 1 ? ' selected' : ' selected');
        }

        toggle.checked = selectedCount === itemCheckboxes.length;
        toggle.indeterminate = selectedCount > 0 && selectedCount < itemCheckboxes.length;

        if (applyButton) {
            applyButton.disabled = selectedCount === 0;
        }
    };

    toggle.addEventListener('change', function () {
        itemCheckboxes.forEach(function (checkbox) {
            checkbox.checked = toggle.checked;
        });

        updateSelectionState();
    });

    itemCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectionState);
    });

    if (actionSelect) {
        actionSelect.addEventListener('change', updateSelectionState);
    }

    updateSelectionState();
}

function initUmaBulkForms(root) {
    root.querySelectorAll('form').forEach(function (form) {
        initUmaBulkForm(form);
    });
}

function initUmaAsyncPanels(root) {
    var panels = root.querySelectorAll('[data-uma-async-container]');

    panels.forEach(function (panel) {
        if (panel.dataset.umaAsyncLoaded === 'true') {
            return;
        }

        panel.dataset.umaAsyncLoaded = 'loading';

        var requestBody = new URLSearchParams({
            action: panel.dataset.umaAsyncAction || '',
            nonce: (window.umaAdmin && window.umaAdmin.loadUnusedNonce) || ''
        });

        fetch((window.umaAdmin && window.umaAdmin.ajaxUrl) || window.ajaxurl || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: requestBody.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.data || !payload.data.html) {
                throw new Error('Invalid payload');
            }

            panel.innerHTML = payload.data.html;
            panel.dataset.umaAsyncLoaded = 'true';
            initUmaBulkForms(panel);
        }).catch(function () {
            var message = (window.umaAdmin && window.umaAdmin.messages && window.umaAdmin.messages.loadError)
                ? window.umaAdmin.messages.loadError
                : 'The image list could not be loaded. Please try Refresh Scan.';

            panel.innerHTML = '<div class="notice notice-error inline"><p>' + message + '</p></div>';
            panel.dataset.umaAsyncLoaded = 'error';
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initUmaBulkForms(document);
    initUmaAsyncPanels(document);
});
