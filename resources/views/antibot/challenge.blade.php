<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verifying your browser…</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f8f9fb;
            color: #1f2430;
        }

        .antibot-card {
            text-align: center;
            padding: 2rem;
        }

        .antibot-spinner {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 1rem;
            border: 3px solid #d7dce3;
            border-top-color: #4a5bf5;
            border-radius: 50%;
            animation: antibot-spin 0.8s linear infinite;
        }

        @keyframes antibot-spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div
        id="antibot-challenge"
        class="antibot-card"
        data-challenge-id="{{ $challengeId }}"
        data-nonce="{{ $nonce }}"
        data-difficulty="{{ $difficulty }}"
        data-verify-url="{{ $verifyUrl }}"
        data-redirect-url="{{ $redirectUrl }}"
    >
        <div class="antibot-spinner" aria-hidden="true"></div>
        <p id="antibot-status">Verifying your browser…</p>
        <noscript><p>Please enable JavaScript to continue.</p></noscript>
    </div>
    <script src="{{ asset('vendor/antibot/js/challenge.js') }}" defer></script>
</body>
</html>
