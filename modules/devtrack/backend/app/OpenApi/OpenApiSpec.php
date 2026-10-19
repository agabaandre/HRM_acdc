<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Email Server API",
 *     version="1.0.0",
 *     description="Central email gateway for external system integrations. Obtain a JWT via client credentials, then send mail through the integration endpoints below."
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="integrationJwt",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Integration JWT from POST /integrations/auth/token (expires in 24 hours)"
 * )
 *
 * @OA\Tag(name="Integration Auth", description="JWT token exchange for external systems")
 * @OA\Tag(name="Integration Mail", description="Send email via integration JWT")
 * @OA\Tag(name="Platform", description="Public platform endpoints")
 */
class OpenApiSpec
{
}
