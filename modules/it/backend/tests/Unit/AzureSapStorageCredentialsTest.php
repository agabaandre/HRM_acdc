<?php

namespace Tests\Unit;

use App\Services\AzureBlobStorageClient;
use App\Support\AzureSapStorageCredentials;
use Tests\TestCase;

class AzureSapStorageCredentialsTest extends TestCase
{
    public function test_parse_connection_string(): void
    {
        $parsed = AzureSapStorageCredentials::parseConnectionString(
            'DefaultEndpointsProtocol=https;AccountName=sapcbp;AccountKey=abc+/=;EndpointSuffix=core.windows.net'
        );

        $this->assertSame('sapcbp', $parsed['account_name']);
        $this->assertSame('abc+/=', $parsed['account_key']);
        $this->assertSame('core.windows.net', $parsed['endpoint_suffix']);
    }

    public function test_resolve_from_config(): void
    {
        config([
            'azure_sap.connection_string' => '',
            'azure_sap.account_name' => 'sapcbp',
            'azure_sap.account_key' => 'dGVzdA==',
            'azure_sap.container' => 'sap',
            'azure_sap.prefix' => 'DEV/',
            'azure_sap.endpoint_suffix' => 'core.windows.net',
            'azure_sap.timeout' => 30,
        ]);

        $resolved = AzureSapStorageCredentials::resolve();
        $this->assertSame('sapcbp', $resolved['account_name']);
        $this->assertSame('dGVzdA==', $resolved['account_key']);
        $this->assertSame('sap', $resolved['container']);
        $this->assertSame('DEV/', $resolved['prefix']);
        $this->assertSame('env', $resolved['sources']['account_name']);
    }

    public function test_resolve_fills_account_from_connection_string(): void
    {
        config([
            'azure_sap.connection_string' => 'DefaultEndpointsProtocol=https;AccountName=fromcs;AccountKey=a2V5;EndpointSuffix=core.windows.net',
            'azure_sap.account_name' => '',
            'azure_sap.account_key' => '',
            'azure_sap.container' => 'sap',
            'azure_sap.prefix' => '',
        ]);

        $resolved = AzureSapStorageCredentials::resolve();
        $this->assertSame('fromcs', $resolved['account_name']);
        $this->assertSame('a2V5', $resolved['account_key']);
        $this->assertSame('env', $resolved['sources']['connection_string']);
    }

    public function test_blob_endpoint(): void
    {
        $client = new AzureBlobStorageClient([
            'account_name' => 'sapcbp',
            'account_key' => 'dGVzdA==',
            'container' => 'sap',
            'endpoint_suffix' => 'core.windows.net',
        ]);
        $this->assertSame('https://sapcbp.blob.core.windows.net', $client->blobEndpoint());
    }
}
