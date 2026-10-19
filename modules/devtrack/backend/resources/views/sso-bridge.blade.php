<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening Development Tracker…</title>
</head>
<body>
    <p>Opening Development Tracker…</p>
    <script>
        (function () {
            try {
                sessionStorage.setItem('email_server.token', @json($token));
                localStorage.removeItem('email_server.token');
            } catch (e) {}
            window.location.replace(@json($redirect));
        })();
    </script>
</body>
</html>
