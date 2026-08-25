<?php

declare(strict_types=1);

beforeEach(function () {
    $this->fakeRedis();
});

it('serves the standalone challenge page', function () {
    $response = $this->get('/anti-bot/challenge');

    $response->assertOk()->assertViewIs('antibot::challenge');
});

it('completes the full solve-and-verify round trip and sets the verification cookie', function () {
    config(['antibot.challenge.default_difficulty' => 8]);

    $page = $this->get('/anti-bot/challenge');
    $page->assertOk();

    $challengeId = $page->viewData('challengeId');
    $nonce = $page->viewData('nonce');
    $difficulty = $page->viewData('difficulty');

    $answer = solveProofOfWork($nonce, $difficulty);

    $response = $this->postJson('/anti-bot/verify', [
        'challenge_id' => $challengeId,
        'answer' => $answer,
    ]);

    $response->assertOk()
        ->assertJson(['verified' => true])
        ->assertCookie('antibot_verified');
});

it('rejects an incorrect answer without setting a cookie', function () {
    config(['antibot.challenge.default_difficulty' => 8]);

    $page = $this->get('/anti-bot/challenge');
    $challengeId = $page->viewData('challengeId');

    $response = $this->postJson('/anti-bot/verify', [
        'challenge_id' => $challengeId,
        'answer' => '000000000000',
    ]);

    $response->assertStatus(422)->assertJson(['verified' => false])
        ->assertCookieMissing('antibot_verified');
});

it('rejects a replayed (already-consumed) challenge answer', function () {
    config(['antibot.challenge.default_difficulty' => 8]);

    $page = $this->get('/anti-bot/challenge');
    $challengeId = $page->viewData('challengeId');
    $nonce = $page->viewData('nonce');
    $difficulty = $page->viewData('difficulty');
    $answer = solveProofOfWork($nonce, $difficulty);

    $this->postJson('/anti-bot/verify', ['challenge_id' => $challengeId, 'answer' => $answer])
        ->assertOk();

    $replay = $this->postJson('/anti-bot/verify', ['challenge_id' => $challengeId, 'answer' => $answer]);

    $replay->assertStatus(422)->assertJson(['verified' => false]);
});

it('validates required fields on the verify endpoint', function () {
    $response = $this->postJson('/anti-bot/verify', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['challenge_id', 'answer']);
});
