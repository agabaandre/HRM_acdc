<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

class IntegrationDocumentation
{
    /**
     * @OA\Post(
     *     path="/integrations/auth/token",
     *     tags={"Integration Auth"},
     *     summary="Exchange client credentials for a JWT",
     *     description="Authenticate your external system with the client_id and client_secret issued in the admin panel. Returns a bearer JWT valid for 24 hours (configurable via JWT_TTL). Use that token in the Authorization header for mail endpoints.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *
     *             @OA\Schema(
     *                 required={"client_id","client_secret"},
     *
     *                 @OA\Property(property="client_id", type="string", example="your-client-id", description="Your integration identifier (same value shown as Client ID in the admin panel)"),
     *                 @OA\Property(property="client_secret", type="string", example="your-client-secret-min-16-chars", description="Secret key issued when the integration was created. Minimum 16 characters. Never a real production value.")
     *             )
     *         ),
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *                 required={"client_id","client_secret"},
     *
     *                 @OA\Property(property="client_id", type="string", example="your-client-id", description="Your integration identifier (same value shown as Client ID in the admin panel)"),
     *                 @OA\Property(property="client_secret", type="string", example="your-client-secret-min-16-chars", description="Secret key issued when the integration was created. Minimum 16 characters. Never a real production value.")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="JWT issued",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=86400),
     *             @OA\Property(property="expires_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="integration",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="IP not allowed")
     * )
     */
    public function token(): void {}

    /**
     * @OA\Post(
     *     path="/integrations/send",
     *     tags={"Integration Mail"},
     *     summary="Send an email",
     *     description="Queue an email for delivery through the Email Server. Requires a valid integration JWT from POST /integrations/auth/token. Delivery is asynchronous — use GET /integrations/logs/{logId} to check status.\n\n**Attachments:** optional on both form and JSON bodies. Each item needs `filename` + base64 `content` (optional `content_type`). Limits: max 10 files, 5 MB each, 15 MB total. In Try it out, expand the attachments array and add items.",
     *     security={{"integrationJwt":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *
     *             @OA\Schema(
     *                 required={"to","subject","body"},
     *
     *                 @OA\Property(property="to", type="string", format="email", example="user@example.com", description="Recipient email address"),
     *                 @OA\Property(property="subject", type="string", maxLength=500, example="Welcome to the portal", description="Email subject line (max 500 characters)"),
     *                 @OA\Property(property="body", type="string", example="<p>Hello from Email Server</p>", description="Email message content. Use HTML tags when is_html is true."),
     *                 @OA\Property(property="is_html", type="string", default="true", example="true", enum={"true","false","1","0"}, description="Set to true if body contains HTML (default). Set to false for plain text."),
     *                 @OA\Property(property="provider_id", type="integer", nullable=true, description="Optional. Leave empty in most cases. Internal numeric ID of a specific email provider (Exchange, SMTP, etc.) configured in the admin panel. When omitted, the server uses the provider linked to your integration, or the system default provider."),
     *                 @OA\Property(property="cc", type="string", example="", description="Optional. Additional recipients to copy (comma-separated email addresses). Leave empty to omit."),
     *                 @OA\Property(property="bcc", type="string", example="", description="Optional. Blind-copy recipients (comma-separated email addresses). Leave empty to omit."),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     maxItems=10,
     *                     description="Optional file attachments. Add an item per file: filename + base64 content. Max 10 files, 5 MB each, 15 MB total.",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"filename","content"},
     *                         @OA\Property(property="filename", type="string", example="invoice.pdf", description="Original file name including extension"),
     *                         @OA\Property(property="content", type="string", example="JVBERi0xLjQKJcTl8uXrp/Og0MTGCjEgMCBvYmo...", description="Base64-encoded file bytes"),
     *                         @OA\Property(property="content_type", type="string", example="application/pdf", description="Optional MIME type")
     *                     )
     *                 )
     *             )
     *         ),
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *                 required={"to","subject","body"},
     *
     *                 @OA\Property(property="to", type="string", format="email", example="user@example.com", description="Recipient email address"),
     *                 @OA\Property(property="subject", type="string", maxLength=500, example="Welcome to the portal", description="Email subject line (max 500 characters)"),
     *                 @OA\Property(property="body", type="string", example="<p>Hello from Email Server</p>", description="Email message content. Use HTML tags when is_html is true."),
     *                 @OA\Property(property="is_html", type="boolean", default=true, example=true, description="Set to true if body contains HTML (default). Set to false for plain text."),
     *                 @OA\Property(property="provider_id", type="integer", nullable=true, description="Optional. Leave empty in most cases. Internal numeric ID of a specific email provider (Exchange, SMTP, etc.) configured in the admin panel. When omitted, the server uses the provider linked to your integration, or the system default provider."),
     *                 @OA\Property(property="cc", type="array", description="Optional additional copy recipients", @OA\Items(type="string", format="email")),
     *                 @OA\Property(property="bcc", type="array", description="Optional blind-copy recipients", @OA\Items(type="string", format="email")),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     maxItems=10,
     *                     description="Optional file attachments. Each item needs filename + base64 content. Max 10 files, 5 MB each, 15 MB total.",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"filename","content"},
     *                         @OA\Property(property="filename", type="string", example="invoice.pdf", description="Original file name including extension"),
     *                         @OA\Property(property="content", type="string", example="JVBERi0xLjQKJcTl8uXrp/Og0MTGCjEgMCBvYmo...", description="Base64-encoded file bytes. A data-URI prefix is allowed."),
     *                         @OA\Property(property="content_type", type="string", example="application/pdf", description="Optional MIME type; guessed from filename when omitted"),
     *                         @OA\Property(property="content_base64", type="string", description="Alias for content"),
     *                         @OA\Property(property="name", type="string", description="Alias for filename"),
     *                         @OA\Property(property="mime_type", type="string", description="Alias for content_type")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email accepted (async delivery)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="log_id", type="integer"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="attachment_count", type="integer", example=1, description="Number of attachments accepted with this send")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Missing or expired JWT"),
     *     @OA\Response(response=422, description="Validation error (including invalid attachments)")
     * )
     */
    public function send(): void {}

    /**
     * @OA\Schema(
     *     schema="EmailAttachment",
     *     type="object",
     *     required={"filename","content"},
     *     description="File attachment for POST /integrations/send",
     *     @OA\Property(property="filename", type="string", example="invoice.pdf", description="Original file name including extension"),
     *     @OA\Property(property="content", type="string", example="JVBERi0xLjQKJcTl8uXrp/Og0MTGCjEgMCBvYmo...", description="Base64-encoded file bytes. A data-URI prefix (data:application/pdf;base64,...) is allowed."),
     *     @OA\Property(property="content_type", type="string", example="application/pdf", description="Optional MIME type; guessed from filename when omitted")
     * )
     */
    public function emailAttachmentSchema(): void {}

    /**
     * @OA\Get(
     *     path="/integrations/status",
     *     tags={"Integration Mail"},
     *     summary="Integration status",
     *     security={{"integrationJwt":{}}},
     *
     *     @OA\Response(response=200, description="Status payload"),
     *     @OA\Response(response=401, description="Missing or expired JWT")
     * )
     */
    public function status(): void {}

    /**
     * @OA\Get(
     *     path="/integrations/logs/{logId}",
     *     tags={"Integration Mail"},
     *     summary="Check delivery status of a queued email",
     *     security={{"integrationJwt":{}}},
     *
     *     @OA\Parameter(name="logId", in="path", required=true, @OA\Schema(type="integer", example=1), description="The log_id returned when you sent the email"),
     *
     *     @OA\Response(response=200, description="Log status (pending, sent, failed)"),
     *     @OA\Response(response=404, description="Log not found for this integration")
     * )
     */
    public function showLog(): void {}

    /**
     * @OA\Get(
     *     path="/health",
     *     tags={"Platform"},
     *     summary="Platform health (database, Redis, queue, cache)",
     *
     *     @OA\Response(response=200, description="All checks passing"),
     *     @OA\Response(response=503, description="One or more checks failed")
     * )
     */
    public function health(): void {}
}
