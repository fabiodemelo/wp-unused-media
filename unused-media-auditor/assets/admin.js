document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
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
    });
});
