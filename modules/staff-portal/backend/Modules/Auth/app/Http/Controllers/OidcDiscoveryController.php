<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RuntimeException;

class OidcDiscoveryController extends Controller
{
    public function discovery(): JsonResponse
    {
        $issuer = rtrim((string) config('app.url'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => url('oauth/authorize'),
            'token_endpoint' => url('oauth/token'),
            'userinfo_endpoint' => url('api/v1/oauth/user'),
            'jwks_uri' => url('oauth/jwks'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ]);
    }

    public function jwks(): JsonResponse
    {
        return response()->json([
            'keys' => [$this->publicJwk()],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function publicJwk(): array
    {
        $path = storage_path('oauth-public.key');
        $pem = @file_get_contents($path);

        if (! is_string($pem) || $pem === '') {
            throw new RuntimeException('OAuth public key not configured.');
        }

        $resource = openssl_pkey_get_public($pem);
        $details = $resource ? openssl_pkey_get_details($resource) : false;
        $rsa = is_array($details) ? ($details['rsa'] ?? null) : null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            throw new RuntimeException('OAuth public key must be a valid RSA public key.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->base64UrlEncode(hash('sha256', $pem, true)),
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
