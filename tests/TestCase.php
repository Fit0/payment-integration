<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;

    protected function login(): User
    {
        $user = User::factory()->create();

        $data = [
            'email' => $user->email,
            'password' => 'password'
        ];

        $response = $this->postJson('/api/login', $data);
        $response->assertStatus(200);

        $this->assertIsString($response['token']);
        $this->assertTrue(str_contains($response['token'], '|'));

        $this->assertIsString($response['expiresAt']);

        $date = Carbon::parse($response['expiresAt']);

        $this->assertTrue($date->gt(now()));

        $seconds = intval(config('sanctum.expiration'));
        $this->assertTrue($date->lte(now()->addSeconds($seconds)));

        return $user;
    }

    protected function getPayload(): array
    {
        return [
            'amount' => $this->faker->numberBetween(1,101),
            'currency' => 'EUR'
        ];
    }

    protected function provider_no_login(int $id, string $uuid): void
    {
        $response = $this->postJson("/api/payment/$uuid", $this->getPayload());

        $response->assertStatus(401)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('message', 'Unauthenticated.'));
    }

    protected function provider_zero_amount(int $id, string $uuid): void
    {
        $user = $this->login();
        $payload = $this->getPayload();

        $payload['amount'] = 0;

        $response = $this->actingAs($user)
            ->postJson("/api/payment/$uuid", $payload);

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json
                    ->where('message', 'The amount field must be at least 1.')
                    ->etc()
            );
    }
}
