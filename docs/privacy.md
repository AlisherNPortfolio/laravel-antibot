# Privacy

## No invasive fingerprinting

This package does not implement, and will not implement in v1, any of the
following:

- Canvas fingerprinting
- WebGL fingerprinting
- Audio fingerprinting
- Font-enumeration fingerprinting
- Any other invasive device-fingerprinting technique

The client-side JavaScript (`resources/js/antibot/challenge.js`) does
exactly one thing: solve a proof-of-work puzzle using the standard Web
Crypto API and POST the result. It does not read canvas data, WebGL
parameters, installed fonts, device sensors, or anything beyond what is
needed to answer the puzzle.

## What is stored, and how

Where IP-derived information must be used as a cache/rate-limit key, it is
hashed first:

```php
hash_hmac('sha256', $ip, config('app.key'));
```

(see `Support\Hashing`) — never the raw IP. This is used for every Redis
key the package writes (rate limits, blocks, challenge failure counts,
crawl-pattern tracking, the trusted-bot cache) and for the optional database
event log.

## The verification cookie

`antibot_verified` contains an authenticated-encrypted, opaque token with
an issued-at timestamp, an expiry, and a random ID — nothing about the
user, their IP, or their session. It is not bound to the requester's IP
(see `docs/security.md` for why) and carries no personally identifying
information.

## Optional database logging

`anti_bot_events` (disabled by default; `antibot.logging.store_database_events`)
stores only hashed identifiers, never raw IPs, session IDs, or user agents:

```text
id, ip_hash, session_hash, path, method, user_agent_hash,
score, decision, reason, metadata, created_at
```

## What is never logged

Regardless of the `logging.enabled` setting, this package never logs:

- Authentication credentials
- Raw cookies (including the verification cookie's actual value)
- The verification token itself
- Challenge secrets (the PoW nonce)
- Raw session IDs

Log lines (`antibot.trusted_bot_verified`, `antibot.request_blocked`, etc.)
carry only a hashed IP, the request path, and non-identifying metadata
(score, decision reason).

## What you, the host application, control

This package cannot make privacy guarantees about data it doesn't touch —
your own application logs, your session driver, and any infrastructure
(Nginx access logs, a CDN, an APM tool) sitting in front of or alongside it
are outside its scope. Review those separately against your own privacy
obligations.
