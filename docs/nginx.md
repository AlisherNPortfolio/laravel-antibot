# Nginx (optional)

`laravel-antibot` is an **application-layer** protection. It runs inside
Laravel, after Nginx (or any other reverse proxy) has already accepted the
connection and forwarded the request to PHP. It cannot protect against pure
volumetric flooding at the network/connection level — that is
infrastructure's job.

Infrastructure-level rate limiting complements this package; it does not
replace it, and this package does not replace it either. Running both is
normal and recommended for public-facing sites.

## A minimal example

```nginx
limit_req_zone $binary_remote_addr zone=antibot_general:10m rate=5r/s;

server {
    location / {
        limit_req zone=antibot_general burst=20 nodelay;
        # ... proxy_pass to PHP-FPM / your app ...
    }
}
```

Tune the rate/burst values for your actual traffic profile — this is a
starting point, not a recommendation for every site.

## Trusted crawlers and infrastructure limits

A request verified as Googlebot/Bingbot by this package bypasses *this
package's* challenge/block logic (see `docs/trusted-bots.md`), but Nginx
has no knowledge of that verification — an `limit_req` zone applies equally
to everyone, crawlers included. If you need to exempt verified crawler IP
ranges from an Nginx-level limit, use Nginx's own `geo`/`map` directives
against the official Google/Bing IP-range files, independently of this
package.

## This package does not require Nginx

Everything above is optional. The package works correctly behind any web
server or none at all (e.g. `php artisan serve` during development) — Nginx
configuration only adds a second, independent layer of protection.
