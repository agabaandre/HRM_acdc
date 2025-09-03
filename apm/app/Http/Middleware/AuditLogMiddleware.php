<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogMiddleware
{
    /**
     * Routes that should be audited.
     */
    private array $auditableRoutes = [
        // Matrix routes
        'matrices.*',
        'matrix.*',
        
        // Non-travel memo routes
        'non-travel.*',
        'non-travel-memo.*',
        
        // Special memo routes
        'special-memo.*',
        'special.*',
        
        // ARF request routes
        'arf.*',
        'request.*',
        
        // Activity routes
        'activities.*',
        'activity.*',
        
        // Jobs routes
        'jobs.*',
        
        // Workflow routes
        'workflows.*',
        'workflow.*',
        
        // Login/logout routes
        'login',
        'logout',
        
        // User management routes
        'users.*',
        'user.*',
    ];

    /**
     * Actions that should be audited.
     */
    private array $auditableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit if response is successful and route should be audited
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            try {
                $this->logAudit($request, $response);
            } catch (\Exception $e) {
                // Log the error but don't break the request
                Log::error('Audit middleware error: ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'error' => $e->getTraceAsString()
                ]);
            }
        }

        return $response;
    }

    /**
     * Log the audit information.
     */
    private function logAudit(Request $request, Response $response): void
    {
        try {
            $routeName = $request->route()?->getName();
            
            // Check if route should be audited
            if (!$this->shouldAuditRoute($routeName, $request->method())) {
                return;
            }

            $user = Auth::user();
            $action = $this->determineAction($request);
            $resourceType = $this->determineResourceType($routeName, $request);
            $resourceId = $this->extractResourceId($request);

            AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => $user ? $user->fname . ' ' . $user->lname : null,
                'user_email' => $user?->work_email,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'route_name' => $routeName,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_values' => $this->getOldValues($request),
                'new_values' => $this->getNewValues($request),
                'description' => $this->generateDescription($action, $resourceType, $resourceId, $user),
                'metadata' => $this->getMetadata($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
            ]);
        }
    }

    /**
     * Check if route should be audited.
     */
    private function shouldAuditRoute(?string $routeName, string $method): bool
    {
        if (!$routeName) {
            return false;
        }

        // Check if method should be audited
        if (!in_array($method, $this->auditableMethods)) {
            return false;
        }

        // Check if route matches any auditable patterns
        foreach ($this->auditableRoutes as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the action based on request method and route.
     */
    private function determineAction(Request $request): string
    {
        $method = $request->method();
        $routeName = $request->route()?->getName();

        // Special cases for specific actions
        if (str_contains($routeName, 'approve')) {
            return 'APPROVE';
        }
        if (str_contains($routeName, 'reject')) {
            return 'REJECT';
        }
        if (str_contains($routeName, 'login')) {
            return 'LOGIN';
        }
        if (str_contains($routeName, 'logout')) {
            return 'LOGOUT';
        }

        // Default based on HTTP method
        return match($method) {
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => 'ACCESS'
        };
    }

    /**
     * Determine resource type from route name.
     */
    private function determineResourceType(?string $routeName, Request $request): string
    {
        if (!$routeName) {
            return 'Unknown';
        }

        // Extract resource type from route name
        if (str_contains($routeName, 'matrix')) {
            return 'Matrix';
        }
        if (str_contains($routeName, 'non-travel')) {
            return 'NonTravelMemo';
        }
        if (str_contains($routeName, 'special')) {
            return 'SpecialMemo';
        }
        if (str_contains($routeName, 'arf') || str_contains($routeName, 'request')) {
            return 'ARFRequest';
        }
        if (str_contains($routeName, 'activity')) {
            return 'Activity';
        }
        if (str_contains($routeName, 'job')) {
            return 'Job';
        }
        if (str_contains($routeName, 'workflow')) {
            return 'Workflow';
        }
        if (str_contains($routeName, 'user')) {
            return 'User';
        }

        return 'System';
    }

    /**
     * Extract resource ID from request.
     */
    private function extractResourceId(Request $request): ?int
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        // Try to get ID from route parameters
        $parameters = $route->parameters();
        
        // Common ID parameter names
        $idKeys = ['id', 'matrix', 'memo', 'activity', 'user', 'workflow'];
        
        foreach ($idKeys as $key) {
            if (isset($parameters[$key]) && is_numeric($parameters[$key])) {
                return (int) $parameters[$key];
            }
        }

        return null;
    }

    /**
     * Get old values for updates.
     */
    private function getOldValues(Request $request): ?array
    {
        // This would need to be implemented based on your specific needs
        // For now, return null as we don't have access to old values in middleware
        return null;
    }

    /**
     * Get new values from request.
     */
    private function getNewValues(Request $request): ?array
    {
        $data = $request->all();
        
        // Remove sensitive data
        $sensitiveKeys = ['password', 'password_confirmation', 'token', '_token'];
        foreach ($sensitiveKeys as $key) {
            unset($data[$key]);
        }

        return empty($data) ? null : $data;
    }

    /**
     * Generate human-readable description.
     */
    private function generateDescription(string $action, string $resourceType, ?int $resourceId, $user): string
    {
        $userName = $user ? $user->fname . ' ' . $user->lname : 'Unknown User';
        $resource = $resourceId ? "{$resourceType} #{$resourceId}" : $resourceType;
        
        return "{$userName} {$action} {$resource}";
    }

    /**
     * Get additional metadata.
     */
    private function getMetadata(Request $request): array
    {
        return [
            'session_id' => $request->session()->getId(),
            'referer' => $request->header('referer'),
            'content_type' => $request->header('content-type'),
            'accept' => $request->header('accept'),
        ];
    }
}
