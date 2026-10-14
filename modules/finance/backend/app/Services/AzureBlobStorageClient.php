<?php

namespace App\Services;

use RuntimeException;

/**
 * Lightweight Azure Blob Storage client using Shared Key REST signing.
 * Avoids a composer dependency for list/download of SAP dump files.
 */
class AzureBlobStorageClient
{
    private string $accountName;

    private string $accountKey;

    private string $container;

    private string $endpointSuffix;

    private string $apiVersion;

    private int $timeout;

    /**
     * @param  array{
     *   account_name?: string,
     *   account_key?: string,
     *   container?: string,
     *   endpoint_suffix?: string,
     *   timeout?: int
     * }  $config
     */
    public function __construct(array $config)
    {
        $this->accountName = trim((string) ($config['account_name'] ?? ''));
        $this->accountKey = trim((string) ($config['account_key'] ?? ''));
        $this->container = trim((string) ($config['container'] ?? 'sap'));
        $this->endpointSuffix = trim((string) ($config['endpoint_suffix'] ?? 'core.windows.net'));
        $this->apiVersion = (string) config('azure_sap.api_version', '2021-08-06');
        $this->timeout = (int) ($config['timeout'] ?? config('azure_sap.timeout', 60));

        if ($this->accountName === '' || $this->accountKey === '') {
            throw new RuntimeException('Azure Blob account name and key are required.');
        }
        if ($this->container === '') {
            throw new RuntimeException('Azure Blob container is required.');
        }
    }

    public function blobEndpoint(): string
    {
        return 'https://'.$this->accountName.'.blob.'.$this->endpointSuffix;
    }

    /**
     * @return list<array{
     *   name: string,
     *   size: int,
     *   last_modified: string|null,
     *   content_type: string|null,
     *   is_directory: bool
     * }>
     */
    public function listBlobs(?string $prefix = null): array
    {
        $query = ['restype' => 'container', 'comp' => 'list'];
        if ($prefix !== null && $prefix !== '') {
            $query['prefix'] = $prefix;
        }

        $xml = $this->request('GET', '/'.$this->container, $query, [
            'comp' => 'list',
            'restype' => 'container',
        ] + ($prefix !== null && $prefix !== '' ? ['prefix' => $prefix] : []));

        $files = [];
        if (! preg_match_all('#<Blob>(.*?)</Blob>#s', $xml, $blobs)) {
            return $files;
        }
        foreach ($blobs[1] as $block) {
            $name = $this->xmlTag($block, 'Name');
            if ($name === '') {
                continue;
            }
            $size = (int) $this->xmlTag($block, 'Content-Length');
            $lastModified = $this->xmlTag($block, 'Last-Modified');
            $contentType = $this->xmlTag($block, 'Content-Type');
            // Zero-byte blobs without a data extension are treated as folder markers.
            $looksLikeFile = (bool) preg_match('/\.(xlsx|xls|csv|txt|tsv|zip|json|xml)$/i', $name);
            $isDir = str_ends_with($name, '/') || ($size === 0 && ! $looksLikeFile);
            $files[] = [
                'name' => $name,
                'size' => $size,
                'last_modified' => $lastModified !== '' ? $lastModified : null,
                'content_type' => $contentType !== '' ? $contentType : null,
                'is_directory' => $isDir,
            ];
        }

        usort($files, static fn ($a, $b) => strcmp($b['last_modified'] ?? '', $a['last_modified'] ?? ''));

        return $files;
    }

    /**
     * @return array{body: string, content_type: string|null, content_length: int}
     */
    public function download(string $blobName): array
    {
        $blobName = $this->normalizeBlobName($blobName);
        $path = '/'.$this->container.'/'.$blobName;
        $body = $this->request('GET', $path, [], [], true);

        return [
            'body' => $body['body'],
            'content_type' => $body['content_type'],
            'content_length' => $body['content_length'],
        ];
    }

    public function delete(string $blobName): void
    {
        $blobName = $this->normalizeBlobName($blobName);
        $path = '/'.$this->container.'/'.$blobName;
        $this->request('DELETE', $path, [], []);
    }

    private function normalizeBlobName(string $blobName): string
    {
        $blobName = ltrim(str_replace('\\', '/', $blobName), '/');
        if ($blobName === '' || str_contains($blobName, '..')) {
            throw new RuntimeException('Invalid blob name.');
        }

        return $blobName;
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $canonicalQuery  sorted query params for signing
     * @return string|array{body: string, content_type: string|null, content_length: int}
     */
    private function request(
        string $method,
        string $path,
        array $query,
        array $canonicalQuery,
        bool $withMeta = false,
    ): string|array {
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $canonicalHeaders = "x-ms-date:{$date}\nx-ms-version:{$this->apiVersion}";
        $canonicalResource = '/'.$this->accountName.$path;
        if ($canonicalQuery !== []) {
            ksort($canonicalQuery);
            foreach ($canonicalQuery as $k => $v) {
                $canonicalResource .= "\n{$k}:{$v}";
            }
        }
        $stringToSign = "{$method}\n\n\n\n\n\n\n\n\n\n\n\n{$canonicalHeaders}\n{$canonicalResource}";
        $rawKey = base64_decode($this->accountKey, true);
        if ($rawKey === false) {
            throw new RuntimeException('Azure account key is not valid base64.');
        }
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $rawKey, true));

        $url = $this->blobEndpoint().$path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not init Azure request.');
        }
        $headers = [
            'x-ms-date: '.$date,
            'x-ms-version: '.$this->apiVersion,
            'Authorization: SharedKey '.$this->accountName.':'.$signature,
        ];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new RuntimeException('Azure Blob request failed: '.($error !== '' ? $error : 'curl error '.$errno));
        }

        $headerBlob = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        if ($status < 200 || $status >= 300) {
            $msg = trim(strip_tags($body));
            if (preg_match('#<Message>([^<]+)</Message>#', $body, $m)) {
                $msg = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
            }
            throw new RuntimeException('Azure Blob HTTP '.$status.($msg !== '' ? ': '.$msg : ''));
        }

        if (! $withMeta) {
            return $body;
        }

        $contentType = null;
        if (preg_match('/^Content-Type:\s*(.+)$/mi', $headerBlob, $m)) {
            $contentType = trim($m[1]);
        }
        $contentLength = strlen($body);
        if (preg_match('/^Content-Length:\s*(\d+)$/mi', $headerBlob, $m)) {
            $contentLength = (int) $m[1];
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
            'content_length' => $contentLength,
        ];
    }

    private function xmlTag(string $block, string $tag): string
    {
        if (preg_match('#<'.preg_quote($tag, '#').'>([^<]*)</'.preg_quote($tag, '#').'>#', $block, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
        }

        return '';
    }
}
