<?php

declare(strict_types=1);

namespace Greew\OAuth2\Test\Client\Provider;

use Greew\OAuth2\Client\Provider\Azure;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class AzureTest extends TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new Azure([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            'tenantId' => 'mock_tenant_id',
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testScopes(): void
    {
        $scopeSeparator = ' ';
        $options = ['scope' => [uniqid('', true), uniqid('', true)]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertStringContainsString($encodedScope, $url);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('/mock_tenant_id/oauth2/v2.0/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/mock_tenant_id/oauth2/v2.0/token', $uri['path']);
    }

    public function testSettingAuthEndpoints(): void
    {
        $customAuthUrl = uniqid('', true);
        $customResourceOwnerUrl = uniqid('', true);
        $customTenantId = uniqid('', true);
        $token = $this->createMock(AccessToken::class);

        $this->provider = new Azure([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            'tenantId' => $customTenantId,
            'urlAuthorize' => $customAuthUrl,
            'urlResourceOwnerDetails' => $customResourceOwnerUrl,
        ]);

        $authUrl = $this->provider->getAuthorizationUrl();
        $this->assertStringContainsString($customAuthUrl, $authUrl);
        $tokenUrl = $this->provider->getBaseAccessTokenUrl([]);
        $this->assertStringContainsString($customAuthUrl, $tokenUrl);
        $resourceOwnerUrl = $this->provider->getResourceOwnerDetailsUrl($token);
        $this->assertStringContainsString($customResourceOwnerUrl, $resourceOwnerUrl);
    }

    public function testGetAccessToken(): void
    {
        $streamInterface = $this->createMock(StreamInterface::class);
        $streamInterface
            ->method('__toString')
            ->willReturn('{"access_token":"mock_access_token","authentication_token":"","code":"","expires_in":3600,"refresh_token":"mock_refresh_token","scope":"","state":"","token_type":""}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($streamInterface);
        $response->method('getHeader')->willReturn(['content-type' => 'json']);

        $client = $this->createMock('GuzzleHttp\ClientInterface');
        $client
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData(): void
    {
        $userId = rand(1000, 9999);

        $postResponseStreamInterface = $this->createMock(StreamInterface::class);
        $postResponseStreamInterface
            ->method('__toString')
            ->willReturn('{"access_token":"mock_access_token","authentication_token":"","code":"","expires_in":3600,"refresh_token":"mock_refresh_token","scope":"","state":"","token_type":""}');

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse->method('getBody')->willReturn($postResponseStreamInterface);
        $postResponse->method('getHeader')->willReturn(['content-type' => 'json']);

        $userResponseStreamInterface = $this->createMock(StreamInterface::class);
        $userResponseStreamInterface
            ->method('__toString')
            ->willReturn('{"id": ' . $userId . '}');

        $userResponse = $this->createMock(ResponseInterface::class);
        $userResponse->method('getBody')->willReturn($userResponseStreamInterface);
        $userResponse->method('getHeader')->willReturn(['content-type' => 'json']);

        $client = $this->createMock('GuzzleHttp\ClientInterface');
        $client
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnOnConsecutiveCalls($postResponse, $userResponse);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['id']);
    }

    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $this->expectException(\League\OAuth2\Client\Provider\Exception\IdentityProviderException::class);
        $message = uniqid('', true);

        $postResponseStreamInterface = $this->createMock(StreamInterface::class);
        $postResponseStreamInterface
            ->method('__toString')
            ->willReturn('{"error": "invalid_grant", "error_description": "' . $message . '"}');

        $postResponse = $this->createMock(ResponseInterface::class);
        $postResponse
            ->method('getBody')
            ->willReturn($postResponseStreamInterface);

        $postResponse
            ->method('getHeader')
            ->willReturn(['content-type' => 'json']);

        $postResponse
            ->method('getStatusCode')
            ->willReturn(400);

        $client = $this->createMock('GuzzleHttp\ClientInterface');
        $client
            ->expects($this->once())
            ->method('send')
            ->willReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
