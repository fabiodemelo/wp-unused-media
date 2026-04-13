document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        var toggle = form.querySelector('.uma-toggle-all');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('change', function () {
            form.querySelectorAll('input[name="attachment_ids[]"]').forEach(function (checkbox) {
                checkbox.checked = toggle.checked;
            });
        });
    });
});
