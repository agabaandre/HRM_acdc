<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Opening Helpdesk…</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f4f6f8; color: #2c3e50; }
    </style>
</head>
<body>
    <p>Opening Helpdesk…</p>
    <script>
        (function () {
            try {
                sessionStorage.setItem('helpdesk_api_token', @json($token));
                localStorage.setItem('helpdesk_api_token', @json($token));
            } catch (e) {}
            window.location.replace(@json($redirect));
        })();
    </script>
</body>
</html>
