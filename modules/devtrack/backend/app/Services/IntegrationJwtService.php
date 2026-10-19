<?php

namespace App\Services;

use App\Models\ExternalIntegration;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Auth\AuthenticationException;
use InvalidArgumentException;
use stdClass;

class IntegrationJwtService
{
    public function issue(ExternalIntegration $integration): array
    {
        $secret = $this->secret();
        $ttlMinutes = max(1, (int) config('integration.jwt_ttl', 1440));
        $issuedAt = time();
        $expiresAt = $issuedAt + ($ttlMinutes * 60);

        $payload = [
            'iss' => (string) config('app.url'),
            'sub' => (string) $integration->id,
            'slug' => $integration->slug,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt - $issuedAt,
            'expires_at' => gmdate('c', $expiresAt),
        ];
    }

    public function decode(string $token): stdClass
    {
        try {
            return JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (ExpiredException) {
            throw new AuthenticationException('JWT has expired. Request a new token.');
        } catch (SignatureInvalidException) {
            throw new AuthenticationException('Invalid JWT signature.');
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid JWT.');
        }
    }

    public function integrationFromToken(string $token): ExternalIntegration
    {
        $claims = $this->decode($token);
        $integrationId = isset($claims->sub) ? (int) $claims->sub : 0;

        $integration = ExternalIntegration::query()
            ->where('is_active', true)
            ->with('emailProvider')
            ->find($integrationId);

        if ($integration === null) {
            throw new AuthenticationException('Integration not found or inactive.');
        }

        if (isset($claims->slug) && $claims->slug !== $integration->slug) {
            throw new AuthenticationException('JWT does not match integration.');
        }

        return $integration;
    }

    private function secret(): string
    {
        $secret = (string) config('integration.jwt_secret', '');

        if ($secret === '') {
            throw new InvalidArgumentException('JWT_SECRET is not configured.');
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT_SECRET must be at least 32 characters for HS256.');
        }

        return $secret;
    }
}
