<?php

namespace Modules\Share\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Modules\Auth\Models\PortalUser;
use Modules\Share\Services\ShareReferenceDataService;

/**
 * CI3-compatible Share endpoints consumed by APM (payload shapes unchanged).
 */
class ShareReferenceApiController extends Controller
{
    public function __construct(
        protected ShareReferenceDataService $data,
    ) {}

    public function getCurrentStaff(Request $request): JsonResponse
    {
        $filters = $request->query();
        $limit = $request->filled('limit') ? (int) $request->query('limit') : null;
        $start = $request->filled('start') ? (int) $request->query('start') : null;
        unset($filters['limit'], $filters['start'], $filters['token']);

        try {
            $rows = $this->data->currentStaff($filters, $limit, $start);

            return response()->json($rows, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Database error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function divisions(): JsonResponse
    {
        try {
            return response()->json($this->data->divisions(), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Database error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function directorates(): JsonResponse
    {
        try {
            return response()->json($this->data->directorates(), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Database error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Issue a Share API JWT using HTTP Basic Auth (same credentials as CI share).
     */
    public function issueToken(Request $request): JsonResponse
    {
        $email = $request->getUser();
        $password = $request->getPassword();
        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return response()->json([
                'success' => false,
                'error' => 'HTTP Basic Authentication required',
            ], 401, ['WWW-Authenticate' => 'Basic realm="Staff Share API"']);
        }

        $user = PortalUser::query()
            ->where('status', 1)
            ->whereHas('staff', fn ($q) => $q->where('work_email', $email))
            ->first();

        if (! $user || ! $user->password || ! password_verify($password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid credentials',
            ], 401);
        }

        $ttl = max(60, (int) config('share.jwt_ttl', 3600));
        $session = $user->toSessionArray();
        $session['aud'] = (string) config('share.jwt_audience', 'share-api');
        $session['sub'] = (string) $user->user_id;
        $token = SsoJwt::encode($session, $ttl);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'aud' => $session['aud'],
        ]);
    }

    public function openapi(): Response
    {
        $path = module_path('Share', 'resources/openapi/share-api.yaml');
        if (! is_file($path)) {
            return response('OpenAPI spec not found', 404);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    public function docs(): Response
    {
        $specUrl = url('/share/openapi.yaml');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Staff Share API — Swagger</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { display: none; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: {$this->jsonEncode($specUrl)},
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis],
      layout: 'BaseLayout',
      persistAuthorization: true
    });
  </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function jsonEncode(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
