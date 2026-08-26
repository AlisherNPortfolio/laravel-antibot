# Custom Challenge Providers

The bundled challenge is a browser proof-of-work puzzle
(`Providers\ProofOfWorkChallengeProvider`), but the rest of the package only
depends on the `Contracts\ChallengeProvider` contract:

```php
interface ChallengeProvider
{
    public function create(AntiBotContext $context, int $difficulty): Challenge;
    public function verify(Challenge $challenge, string $answer): bool;
}
```

To integrate Turnstile, hCaptcha, reCAPTCHA, or a fully custom mechanism,
implement this contract and bind it in your own service provider — it will
be resolved wherever `ChallengeService` needs a provider:

```php
namespace App\AntiBot;

use AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeProvider;
use AlisherNPortfolio\LaravelAntiBot\DTO\AntiBotContext;
use AlisherNPortfolio\LaravelAntiBot\DTO\Challenge;

final class TurnstileChallengeProvider implements ChallengeProvider
{
    public function create(AntiBotContext $context, int $difficulty): Challenge
    {
        // $difficulty is meaningless for a third-party widget — ignore it,
        // or repurpose it as a hint for which Turnstile widget mode to use.
        return new Challenge(
            id: bin2hex(random_bytes(16)),
            nonce: '', // unused by this provider
            difficulty: 0,
            expiresAt: time() + 300,
        );
    }

    public function verify(Challenge $challenge, string $answer): bool
    {
        // $answer here is whatever your controller/JS submits — e.g. the
        // Turnstile response token. Call the vendor's siteverify API here.
        return Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret'),
            'response' => $answer,
        ])->json('success', false);
    }
}
```

```php
// AppServiceProvider::register()
$this->app->singleton(
    \AlisherNPortfolio\LaravelAntiBot\Contracts\ChallengeProvider::class,
    \App\AntiBot\TurnstileChallengeProvider::class,
);
```

Registering your own provider this way overrides the package's own binding
(the last one registered wins), so make sure your `AppServiceProvider`
(or wherever you bind it) boots after `AntiBotServiceProvider` — Laravel's
default provider ordering already satisfies this for providers listed in
`bootstrap/providers.php`/`config/app.php` after package auto-discovery.

## Custom challenge storage

`Contracts\ChallengeStore` (default: `Stores\RedisChallengeStore`) can also
be swapped independently — for example, to store challenges in a different
Redis database or an entirely different backend. Requirements if you
implement your own:

- Challenge IDs must be cryptographically random (`random_bytes()`), never
  `uniqid()`/`rand()`.
- `consume()` must be atomic (fetch-and-delete in one operation) so a
  correct answer can never be accepted twice, even under concurrent
  requests for the same challenge ID.
- Respect the challenge's own `expiresAt` — don't let a re-persisted
  challenge (e.g. after an attempt increment) live longer than its
  original expiry.

## Custom responses

`Http\Middleware\AntiBotMiddleware` is intentionally not `final`, and its
`challengeResponse()`/`blockResponse()` methods are `protected`. To fully
customize the HTML/JSON returned for CHALLENGE/BLOCK, extend the class:

```php
class MyAntiBotMiddleware extends \AlisherNPortfolio\LaravelAntiBot\Http\Middleware\AntiBotMiddleware
{
    protected function blockResponse($request, $result): \Symfony\Component\HttpFoundation\Response
    {
        return response()->view('errors.blocked', [], 403);
    }
}
```

Then bind your subclass to the middleware alias instead of the package's:

```php
$router->aliasMiddleware('antibot', MyAntiBotMiddleware::class);
```
