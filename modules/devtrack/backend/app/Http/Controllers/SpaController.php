<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $index = dirname(base_path()).'/ui/index.html';

        if (! is_file($index)) {
            return response(
                'DevTrack UI is not built. From the project folder run: cd frontend && npm install && npm run build',
                503,
            )->header('Content-Type', 'text/plain');
        }

        $html = (string) file_get_contents($index);
        $base = rtrim($request->getBasePath(), '/');
        $href = ($base === '' ? '' : $base).'/';
        $nonce = base64_encode(random_bytes(16));

        $inject = '<base href="'.e($href).'">'
            .'<meta name="app-base" content="'.e(rtrim($href, '/')).'">'
            .'<meta name="csp-nonce" content="'.$nonce.'">';

        $html = (string) preg_replace('/<meta name="csp-nonce"[^>]*>/', '', $html, 1);
        $html = (string) preg_replace('/<head[^>]*>/i', '$0'.$inject, $html, 1);

        $csp = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none';"
            ." script-src 'self' 'nonce-{$nonce}';"
            ." style-src 'self' 'nonce-{$nonce}' 'unsafe-inline';"
            ." font-src 'self' data:;"
            ." img-src 'self' data: blob:;"
            ." connect-src 'self';";

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', $csp)
            // Never cache the SPA shell — stale index.html points at deleted Vite chunks.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }
}
