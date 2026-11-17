<script>
    (function () {
        function scrubSensitiveInputs() {
            document.querySelectorAll('input[type="password"], input[name="password"], input[name="current_password"], input[name="new_password"], input[name="new_password_confirmation"], input[name="password_confirmation"]').forEach(function (input) {
                input.value = '';
            });
        }

        window.addEventListener('pagehide', scrubSensitiveInputs);

        window.addEventListener('pageshow', function (event) {
            scrubSensitiveInputs();

            if (event.persisted) {
                window.location.reload();
            }
        });

        scrubSensitiveInputs();
    })();
</script>
