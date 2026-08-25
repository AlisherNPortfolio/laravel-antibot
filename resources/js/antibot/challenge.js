(function () {
    'use strict';

    function hexHasLeadingZeroBits(hexHash, bits) {
        var nibbles = Math.ceil(bits / 4);
        var prefix = hexHash.slice(0, nibbles);
        var binary = '';

        for (var i = 0; i < prefix.length; i++) {
            binary += parseInt(prefix[i], 16).toString(2).padStart(4, '0');
        }

        return binary.slice(0, bits) === '0'.repeat(bits);
    }

    async function sha256Hex(message) {
        var data = new TextEncoder().encode(message);
        var digest = await crypto.subtle.digest('SHA-256', data);
        var bytes = new Uint8Array(digest);
        var hex = '';

        for (var i = 0; i < bytes.length; i++) {
            hex += bytes[i].toString(16).padStart(2, '0');
        }

        return hex;
    }

    async function solve(nonce, difficulty, onProgress) {
        var counter = 0;

        while (true) {
            var hash = await sha256Hex(nonce + counter);

            if (hexHasLeadingZeroBits(hash, difficulty)) {
                return counter;
            }

            counter++;

            if (onProgress && counter % 2000 === 0) {
                onProgress(counter);
                // Yield back to the event loop periodically so the tab stays responsive.
                await new Promise(function (resolve) {
                    setTimeout(resolve, 0);
                });
            }
        }
    }

    async function init() {
        var root = document.getElementById('antibot-challenge');

        if (!root) {
            return;
        }

        var nonce = root.dataset.nonce;
        var difficulty = parseInt(root.dataset.difficulty, 10);
        var verifyUrl = root.dataset.verifyUrl;
        var challengeId = root.dataset.challengeId;
        var redirectUrl = root.dataset.redirectUrl;
        var statusEl = document.getElementById('antibot-status');

        if (statusEl) {
            statusEl.textContent = 'Verifying your browser…';
        }

        var answer;

        try {
            answer = await solve(nonce, difficulty, function (n) {
                if (statusEl) {
                    statusEl.textContent = 'Verifying your browser… (' + n + ')';
                }
            });
        } catch (error) {
            if (statusEl) {
                statusEl.textContent = 'Your browser could not complete verification.';
            }

            return;
        }

        var response;

        try {
            response = await fetch(verifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    challenge_id: challengeId,
                    answer: String(answer),
                    redirect: redirectUrl,
                }),
            });
        } catch (error) {
            if (statusEl) {
                statusEl.textContent = 'Network error. Please retry.';
            }

            return;
        }

        var result = await response.json().catch(function () {
            return {};
        });

        if (response.ok && result.verified) {
            window.location.href = result.redirect || redirectUrl || '/';

            return;
        }

        if (statusEl) {
            statusEl.textContent = 'Verification failed. Reloading…';
        }

        window.location.reload();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
