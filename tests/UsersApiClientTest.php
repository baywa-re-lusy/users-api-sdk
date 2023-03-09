<?php

namespace BayWaReLusy\UsersAPI\Test;

use BayWaReLusy\JwtAuthentication\UserIdentity;
use BayWaReLusy\UsersAPI\SDK\SubsidiaryEntity;
use BayWaReLusy\UsersAPI\SDK\UserEntity;
use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use BayWaReLusy\UsersAPI\SDK\UsersApiException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;
use Ramsey\Uuid\Uuid;

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
            'https://api.domain.com',
            'https://api.domain.com/token',
            'client-id',
            'client-secret',
            $this->tokenCacheMock,
            $this->usersCacheMock,
            new HttpClient(['handler' => $handlerStack]),
            $this->loggerMock
        );
    }

    /**
     * Test the GET /users call.
     * -> Users are fetched from the cache
     * -> No call to token endpoint or token cache necessary
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUsers_UsersCacheHit(): void
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
        $this->validateUsersProperties($users);
    }

    /**
     * Test the GET /users call.
     * -> Users are not fetched from the cache
     * -> Token found in the token cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUsers_UsersCacheMiss_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the users
        $this->mockCacheMissForUsersCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/users.json'))
        );

        // Execute the call
        $users = $this->instance->getUsers();

        // Verify resulting users
        $this->validateUsersProperties($users);

        // Verify if HTTP requests have been made correctly
        $this->validateUsersRequest(0);
    }

    /**
     * Test the GET /users call.
     * -> Users are not fetched from the cache
     * -> Token not found in the token cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUsers_UsersCacheMiss_TokenCacheMiss(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheMiss();

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
        $this->validateUsersProperties($users);

        // Verify if HTTP requests have been made correctly
        $this->validateTokenRequest();
        $this->validateUsersRequest(1);
    }

    /**
     * Test the GET /users call.
     * -> Token cache throws exception
     * -> No call to users cache or API
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUsers_CacheError(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockCacheException();

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

    /**
     * Test the GET /users call.
     * -> Token cache miss
     * -> Token request call throws exception
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
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

    /**
     * Test the GET /users call.
     * -> Token cache hit
     * -> User GET request throws exception
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUsers_UserGetException_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

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

    /**
     * Test the GET /subsidiaries call.
     * -> Token cache hit
     * -> Subsidiaries not found in cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetSubsidiaries_SubsidiaryCacheMiss_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

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
        $this->validateSubsidiariesRequest(0);
    }

    public static function dataProvider_UserFilter(): array
    {
        $userId = '88e1260c-4ad9-438b-8c42-dfa2397f65bc';

        $user = new UserEntity();
        $user->setId($userId);

        $identity = new UserIdentity();
        $identity->setId($userId);

        return
            [
                [$user],
                [$identity],
            ];
    }

    /**
     * Test the GET /subsidiaries call.
     * -> Token cache hit
     * -> Subsidiaries not found in cache
     * -> Filtered by User/Identity
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     *
     * @dataProvider dataProvider_UserFilter
     */
    public function testGetSubsidiaries_SubsidiaryCacheMiss_TokenCacheHit_FilteredByUser(
        UserEntity|UserIdentity $user
    ): void {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the subsidiaries
        $this->mockCacheMissForSubsidiariesCall($user);

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/subsidiaries.json'))
        );

        // Execute the call
        $subsidiaries = $this->instance->getSubsidiaries($user);

        // Verify resulting users
        $this->validateSubsidiaryProperties($subsidiaries);

        // Verify if HTTP requests have been made correctly
        $this->validateSubsidiariesRequest(0);

        // Verify that the request is filtered by User
        parse_str($this->httpRequestHistoryContainer[0]['request']->getUri()->getQuery(), $queryParams);
        $this->assertEquals($user->getId(), $queryParams['user']);
    }

    /**
     * Test the GET /users call.
     * -> Token cache miss
     * -> Subsidiaries not found in cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetSubsidiaries_SubsidiaryCacheMiss_TokenCacheMiss(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheMiss();

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
        $this->validateTokenRequest();
        $this->validateSubsidiariesRequest(1);
    }

    /**
     * Test the GET /users call.
     * -> Token cache hit
     * -> Subsidiaries GET call throws exception
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetSubsidiaries_SubsidiaryGetException_TokenCacheHit(): void
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

    /**
     * Test the GET /users call.
     * -> Subsidiaries found in cache
     * -> No call to token cache or API necessary
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetSubsidiaries_SubsidiaryCacheHit(): void
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

    /**
     * Test the GET /users/<userId> call.
     * -> User is fetched from the cache
     * -> No call to token endpoint or token cache necessary
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_UserCacheHit(): void
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
            ->willReturn($this->returnValue(
                (new UserEntity())
                    ->setId('c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
                    ->setEmail('john.doe@email.com')
                    ->setEmailVerified(true)
                    ->setUsername('john.doe')
                    ->setRoles(['role1', 'role2'])
            ));

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUser_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Execute the call
        $user = $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify resulting users
        $this->validateUserProperties($user);
    }

    /**
     * Test the GET /users/<userId> call.
     * -> User isn't fetched from the cache
     * -> Token found in the token cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_UserCacheMiss_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the users
        $this->mockCacheMissForUserCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/user.json'))
        );

        // Execute the call
        $user = $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify result(s) and request(s)
        $this->validateUserProperties($user);
        $this->validateUserRequest(0);
    }

    /**
     * Test the GET /users/<userId> call.
     * -> User isn't fetched from the cache
     * -> Token not found in the token cache
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_UserCacheMiss_TokenCacheMiss(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheMiss();

        // Mock the cache miss for the users
        $this->mockCacheMissForUserCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], '{"access_token": "access-token", "expires_in": "60"}'),
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/user.json'))
        );

        // Execute the call
        $user = $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify result(s) and request(s)
        $this->validateUserProperties($user);
        $this->validateTokenRequest();
        $this->validateUserRequest(1);
    }

    /**
     * Test the GET /users/<userId> call.
     * -> Token cache throws exception
     * -> No call to users cache or API
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_CacheError(): void
    {
        // Mock the Cache exception for the access token call
        $this->mockCacheException();

        // Mock the cache miss for the user
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
            ->with('usersApiUser_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * Test the GET /users/<userId> call.
     * -> Token cache miss
     * -> Token request call throws exception
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_TokenRequestException(): void
    {
        // Mock the Cache miss for the access token call
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
            ->with('usersApiUser_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(new ClientException('token request error'));

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * Test the GET /users/<userId> call.
     * -> Token cache hit
     * -> User GET request throws exception
     *
     * @return void
     * @throws UsersApiException
     * @throws Exception
     */
    public function testGetUser_UserGetException_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

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
            ->with('usersApiUser_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(new ClientException('user get exception'));

        $this->expectException(UsersApiException::class);

        // Execute the call
        $this->instance->getUser('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * @param UserEntity[] $users
     * @return void
     */
    protected function validateUsersProperties(array $users): void
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

    protected function validateUserProperties(UserEntity $user): void
    {
        $this->assertEquals('c84056a1-8d36-46c4-ae15-e3cb3db18ed2', $user->getId());
        $this->assertEquals('john.doe', $user->getUsername());
        $this->assertEquals('john.doe@email.com', $user->getEmail());
        $this->assertTrue($user->getEmailVerified());
        $this->assertEquals(['role1', 'role2'], $user->getRoles());
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

    /**
     * @return void
     */
    protected function validateTokenRequest(): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('POST', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/token', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());
        $this->assertEquals(
            'application/json',
            $this->httpRequestHistoryContainer[0]['request']->getHeader('Accept')[0]
        );
        $this->assertEquals(
            'application/x-www-form-urlencoded',
            $this->httpRequestHistoryContainer[0]['request']->getHeader('Content-Type')[0]
        );
    }

    protected function validateUsersRequest(int $nb): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[$nb]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[$nb]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[$nb]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getHost());
        $this->assertEquals('/users', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getPath());
        $this->assertEquals(
            'application/json',
            $this->httpRequestHistoryContainer[$nb]['request']->getHeader('Accept')[0]
        );
    }

    protected function validateUserRequest(int $nb): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[$nb]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[$nb]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[$nb]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getHost());
        $this->assertEquals(
            '/users/c84056a1-8d36-46c4-ae15-e3cb3db18ed2',
            $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getPath()
        );
        $this->assertEquals(
            'application/json',
            $this->httpRequestHistoryContainer[$nb]['request']->getHeader('Accept')[0]
        );
    }

    protected function validateSubsidiariesRequest(int $nb): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[$nb]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[$nb]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[$nb]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getHost());
        $this->assertEquals('/subsidiaries', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getPath());
        $this->assertEquals(
            'application/json',
            $this->httpRequestHistoryContainer[$nb]['request']->getHeader('Accept')[0]
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function mockTokenCacheMiss(): void
    {
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
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function mockTokenCacheHit(): void
    {
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
    }

    /**
     * @return void
     * @throws Exception
     */
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
            ->with(600);
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

    /**
     * @return void
     * @throws Exception
     */
    protected function mockCacheMissForUserCall(): void
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
                    $value->getId() === 'c84056a1-8d36-46c4-ae15-e3cb3db18ed2' &&
                    $value->getUsername() === 'john.doe' &&
                    $value->getEmail() === 'john.doe@email.com' &&
                    $value->getEmailVerified() === true;
            }))
            ->will($this->returnSelf());
        $usersCacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(600);
        $usersCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiUser_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->will($this->returnValue($usersCacheItemMock));
        $this->usersCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($usersCacheItemMock);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function mockCacheMissForSubsidiariesCall(UserEntity|UserIdentity $user = null): void
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
            ->with($user ? 600 : 86400);
        $subsidiariesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->usersCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with($user ? 'usersApiSubsidiaries_' . $user->getId() : 'usersApiSubsidiaries')
            ->will($this->returnValue($subsidiariesCacheItemMock));
        $this->usersCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($subsidiariesCacheItemMock);
    }

    /**
     * @throws Exception
     */
    protected function mockCacheException(): void
    {
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
            ->willThrowException(new InvalidArgumentException('cache error'));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');
    }
}
