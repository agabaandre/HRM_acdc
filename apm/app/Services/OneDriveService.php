<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class OneDriveService
{
    protected $config;
    protected $accessToken;

    public function __construct()
    {
        $this->config = config('backup.onedrive');
    }

    /**
     * Get access token using existing Exchange OAuth
     */
    protected function getAccessToken()
    {
        try {
            require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
            $exchangeConfig = config('exchange-email');
            
            $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
                $this->config['tenant_id'] ?: $exchangeConfig['tenant_id'],
                $this->config['client_id'] ?: $exchangeConfig['client_id'],
                $this->config['client_secret'] ?: $exchangeConfig['client_secret'],
                $exchangeConfig['redirect_uri'],
                'https://graph.microsoft.com/.default', // OneDrive requires Files.ReadWrite scope
                $exchangeConfig['auth_method']
            );
            
            return $oauth->getAccessToken();
        } catch (Exception $e) {
            Log::error('Failed to get OneDrive access token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get or create backup folder in OneDrive
     */
    protected function getBackupFolderId()
    {
        try {
            $accessToken = $this->getAccessToken();
            $folderName = $this->config['folder_name'];
            
            // Check if folder exists
            $url = 'https://graph.microsoft.com/v1.0/me/drive/root/children';
            $response = $this->makeRequest($url, 'GET', null, $accessToken);
            
            if (isset($response['value'])) {
                foreach ($response['value'] as $item) {
                    if ($item['name'] === $folderName && isset($item['folder'])) {
                        return $item['id'];
                    }
                }
            }
            
            // Create folder if it doesn't exist
            $url = 'https://graph.microsoft.com/v1.0/me/drive/root/children';
            $data = [
                'name' => $folderName,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename'
            ];
            
            $response = $this->makeRequest($url, 'POST', $data, $accessToken);
            
            if (isset($response['id'])) {
                return $response['id'];
            }
            
            throw new Exception('Failed to create OneDrive folder');
            
        } catch (Exception $e) {
            Log::error('Failed to get/create OneDrive folder', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload file to OneDrive
     */
    public function uploadFile($filePath, $filename)
    {
        if (!$this->config['enabled']) {
            return false;
        }
        
        try {
            $accessToken = $this->getAccessToken();
            $folderId = $this->getBackupFolderId();
            
            // Read file content
            $fileContent = file_get_contents($filePath);
            $fileSize = filesize($filePath);
            
            // For files larger than 4MB, use upload session
            if ($fileSize > 4 * 1024 * 1024) {
                return $this->uploadLargeFile($filePath, $filename, $folderId, $accessToken);
            }
            
            // Upload small file directly
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/{$folderId}:/{$filename}:/content";
            
            $response = $this->makeRequest($url, 'PUT', $fileContent, $accessToken, [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $fileSize
            ]);
            
            if (isset($response['id'])) {
                Log::info('File uploaded to OneDrive', [
                    'file' => $filename,
                    'size' => $fileSize
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error('OneDrive upload failed', [
                'error' => $e->getMessage(),
                'file' => $filename
            ]);
            return false;
        }
    }

    /**
     * Upload large file using upload session
     */
    protected function uploadLargeFile($filePath, $filename, $folderId, $accessToken)
    {
        try {
            // Create upload session
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/{$folderId}:/{$filename}:/createUploadSession";
            $data = [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'replace',
                    'name' => $filename
                ]
            ];
            
            $session = $this->makeRequest($url, 'POST', $data, $accessToken);
            
            if (!isset($session['uploadUrl'])) {
                throw new Exception('Failed to create upload session');
            }
            
            $uploadUrl = $session['uploadUrl'];
            $fileSize = filesize($filePath);
            $chunkSize = 320 * 1024; // 320KB chunks
            $handle = fopen($filePath, 'rb');
            
            $bytesUploaded = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                $chunkSizeActual = strlen($chunk);
                
                $range = "bytes {$bytesUploaded}-" . ($bytesUploaded + $chunkSizeActual - 1) . "/{$fileSize}";
                
                $response = $this->makeRequest($uploadUrl, 'PUT', $chunk, null, [
                    'Content-Length: ' . $chunkSizeActual,
                    'Content-Range: ' . $range
                ]);
                
                $bytesUploaded += $chunkSizeActual;
            }
            
            fclose($handle);
            
            Log::info('Large file uploaded to OneDrive', [
                'file' => $filename,
                'size' => $fileSize
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Large file upload failed', [
                'error' => $e->getMessage(),
                'file' => $filename
            ]);
            return false;
        }
    }

    /**
     * Make HTTP request
     */
    protected function makeRequest($url, $method = 'GET', $data = null, $accessToken = null, $headers = [])
    {
        $ch = curl_init();
        
        $defaultHeaders = [
            'Content-Type: application/json'
        ];
        
        if ($accessToken) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $accessToken;
        }
        
        $headers = array_merge($defaultHeaders, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        if ($data !== null) {
            if (is_string($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? 'Unknown error';
            throw new Exception("HTTP {$httpCode}: {$errorMsg}");
        }
        
        return $decoded ?: $response;
    }
}

