<?php

namespace BayWaReLusy\UsersAPI\Test;

use BayWaReLusy\UsersAPI\SDK\SubsidiaryEntity;
use BayWaReLusy\UsersAPI\SDK\UserEntity;
use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use BayWaReLusy\UsersAPI\SDK\UsersApiException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;

class UsersApiClientTest extends TestCase
{
    protected UsersApiClient $instance;
    protected MockObject $tokenCacheMock;
    protected MockObject $usersCacheMock;
    protected MockObject $loggerMock;
    protected MockHandler $guzzleMockHandler;
    protected array $httpRequestHistoryContainer = [];

    protected function setUp(): void
    {
        $this->tokenCacheMock  = $this->createMock(CacheItemPoolInterface::class);
        $this->usersCacheMock  = $this->createMock(CacheItemPoolInterface::class);
        $this->loggerMock      = $this->createMock(LoggerInterface::class);

        $this->guzzleMockHandler = new MockHandler();
        $handlerStack            = HandlerStack::create($this->guzzleMockHandler);

        $handlerStack->push(Middleware::history($this->httpRequestHistoryContainer));

        $this->instance = new UsersApiClient(
            'https://my-api.domain.com/users',
            'https://my-api.domain.com/subsidiaries',
            'https://my-api.domain.com/token',
            'client-id',
            'client-secret',
            $this->tokenCacheMock,
            $this->usersCacheMock,
            new HttpClient(['handler' => $handlerStack]),
            $this->loggerMock
        );
    }

    public function testGetUsers_UsersHit(): void
    {
        // If the User cache hits, there is no call to the token cache
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('getItem');

        // Mock the cache hit for the users
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue([
                (new UserEntity())
                    ->setId('c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
                    ->setEmail('john.doe@email.com')
                    ->setEmailVerified(true)
                    ->setUsername('john.doe')
                    ->setRoles(['role1', 'role2']),
                (new UserEntity())
                    ->setId('05991cfa-84a4-4c7f-9486-7d25c6119238')
                    ->setEmail('jane.doe@email.com')
                    ->setEmailVerified(false)
                    ->setUsername('jane.doe')
                    ->setRoles(['role2', 'role3']),
            ]));

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUsers')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Execute the call
        $users = $this->instance->getUsers();

        // Verify resulting users
        $this->validateUserProperties($users);
    }

    public function testGetUsers_UsersMiss_TokenHit(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue('access-token'));

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache miss for the users
        $this->mockCacheMissForUsersCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/users.json'))
        );

        // Execute the call
        $users = $this->instance->getUsers();

        // Verify resulting users
        $this->validateUserProperties($users);

        // Verify if HTTP requests have been made correctly
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/users', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());
    }

    public function testGetUsers_TokenMiss(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with('access-token')
            ->will($this->returnSelf());
        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50)
            ->will($this->returnSelf());
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        // Mock the cache miss for the users
        $this->mockCacheMissForUsersCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], '{"access_token": "access-token", "expires_in": "60"}'),
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/users.json'))
        );

        // Execute the call
        $users = $this->instance->getUsers();

        // Verification
        $this->validateUserProperties($users);

        // Verify if HTTP requests have been made correctly
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('POST', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/token', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());

        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[1]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[1]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[1]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[1]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[1]['request']->getUri()->getHost());
        $this->assertEquals('/users', $this->httpRequestHistoryContainer[1]['request']->getUri()->getPath());
    }

    /**
     * @param UserEntity[] $users
     * @return void
     */
    protected function validateUserProperties(array $users): void
    {
        // Check the number of returned users
        $this->assertCount(2, $users);

        // Check that the results are User entities
        $this->assertInstanceOf(UserEntity::class, $users[0]);
        $this->assertInstanceOf(UserEntity::class, $users[1]);

        // Check if the properties are set correctly
        $this->assertEquals('c84056a1-8d36-46c4-ae15-e3cb3db18ed2', $users[0]->getId());
        $this->assertEquals('05991cfa-84a4-4c7f-9486-7d25c6119238', $users[1]->getId());

        $this->assertEquals('john.doe', $users[0]->getUsername());
        $this->assertEquals('jane.doe', $users[1]->getUsername());

        $this->assertEquals('john.doe@email.com', $users[0]->getEmail());
        $this->assertEquals('jane.doe@email.com', $users[1]->getEmail());

        $this->assertTrue($users[0]->getEmailVerified());
        $this->assertFalse($users[1]->getEmailVerified());

        $this->assertEquals(['role1', 'role2'], $users[0]->getRoles());
        $this->assertEquals(['role2', 'role3'], $users[1]->getRoles());
    }

    public function testGetUsers_CacheError(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->never())
            ->method('isHit');
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->willThrowException(new InvalidArgumentException('cache error'));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache hit for the users
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUsers')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUsers();
    }

    public function testGetUsers_TokenRequestException(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->never())
            ->method('expiresAfter');
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache hit for the users
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUsers')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(new ClientException('token request error'));

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUsers();
    }

    public function testGetUsers_UserGetException(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue('access-token'));

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache miss for the users
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUsers')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(new ClientException('user get exception'));

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUsers();
    }

    public function testGetSubsidiaries_SubsidiariesMiss_TokenHit(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue('access-token'));

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache miss for the subsidiaries
        $this->mockCacheMissForSubsidiariesCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/subsidiaries.json'))
        );

        // Execute the call
        $subsidiaries = $this->instance->getSubsidiaries();

        // Verify resulting users
        $this->validateSubsidiaryProperties($subsidiaries);

        // Verify if HTTP requests have been made correctly
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/subsidiaries', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());
    }

    public function testGetSubsidiaries_TokenMiss(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with('access-token')
            ->will($this->returnSelf());
        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50)
            ->will($this->returnSelf());
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        // Mock the cache miss for the subsidiaries
        $this->mockCacheMissForSubsidiariesCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], '{"access_token": "access-token", "expires_in": "60"}'),
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/subsidiaries.json'))
        );

        // Execute the call
        $subsidiaries = $this->instance->getSubsidiaries();

        // Verification
        $this->validateSubsidiaryProperties($subsidiaries);

        // Verify if HTTP requests have been made correctly
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('POST', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/token', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());

        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[1]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[1]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[1]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[1]['request']->getUri()->getScheme());
        $this->assertEquals('my-api.domain.com', $this->httpRequestHistoryContainer[1]['request']->getUri()->getHost());
        $this->assertEquals('/subsidiaries', $this->httpRequestHistoryContainer[1]['request']->getUri()->getPath());
    }

    /**
     * @param SubsidiaryEntity[] $subsidiaries
     * @return void
     */
    protected function validateSubsidiaryProperties(array $subsidiaries): void
    {
        // Check the number of returned users
        $this->assertCount(2, $subsidiaries);

        // Check that the results are User entities
        $this->assertInstanceOf(SubsidiaryEntity::class, $subsidiaries[0]);
        $this->assertInstanceOf(SubsidiaryEntity::class, $subsidiaries[1]);

        // Check if the properties are set correctly
        $this->assertEquals('1', $subsidiaries[0]->getId());
        $this->assertEquals('2', $subsidiaries[1]->getId());

        $this->assertEquals('Subsidiary 1', $subsidiaries[0]->getName());
        $this->assertEquals('Subsidiary 2', $subsidiaries[1]->getName());
    }

    public function testGetSubsidiaries_SubsidiaryGetException(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue('access-token'));

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache miss for the users
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiSubsidiaries')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(new ClientException('subsidiary get exception'));

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getSubsidiaries();
    }

    protected function mockCacheMissForUsersCall(): void
    {
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $usersCacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->callback(function ($value) {
                return
                    $value[0]->getId() === 'c84056a1-8d36-46c4-ae15-e3cb3db18ed2' &&
                    $value[0]->getUsername() === 'john.doe' &&
                    $value[0]->getEmail() === 'john.doe@email.com' &&
                    $value[0]->getEmailVerified() === true &&
                    $value[1]->getId() === '05991cfa-84a4-4c7f-9486-7d25c6119238' &&
                    $value[1]->getUsername() === 'jane.doe' &&
                    $value[1]->getEmail() === 'jane.doe@email.com' &&
                    $value[1]->getEmailVerified() === false;
            }))
            ->will($this->returnSelf());
        $usersCacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(60);
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUsers')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($usersCacheItemMock);
    }

    protected function mockCacheMissForSubsidiariesCall(): void
    {
        $subsidiariesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $subsidiariesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(false));
        $subsidiariesCacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->callback(function ($value) {
                return
                    $value[0]->getId() === '1' &&
                    $value[0]->getName() === 'Subsidiary 1' &&
                    $value[1]->getId() === '2' &&
                    $value[1]->getName() === 'Subsidiary 2';
            }))
            ->will($this->returnSelf());
        $subsidiariesCacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400);
        $subsidiariesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiSubsidiaries')
            ->will($this->returnValue($subsidiariesCacheItemMock));
        $this->usersCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($subsidiariesCacheItemMock);
    }

    public function testGetSubsidiaries_SubsidiariesHit(): void
    {
        // If the User cache hits, there is no call to the token cache
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('getItem');

        // Mock the cache hit for the subsidiaries
        $usersCacheItemMock = $this->createMock(CacheItemInterface::class);
        $usersCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn($this->returnValue(true));
        $usersCacheItemMock
            ->expects($this->never())
            ->method('set');
        $usersCacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->returnValue([
                (new SubsidiaryEntity())
                    ->setId('1')
                    ->setName('Subsidiary 1'),
                (new SubsidiaryEntity())
                    ->setId('2')
                    ->setName('Subsidiary 2'),
            ]));

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiSubsidiaries')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Execute the call
        $subsidiaries = $this->instance->getSubsidiaries();

        // Verify resulting users
        $this->validateSubsidiaryProperties($subsidiaries);
    }
}
