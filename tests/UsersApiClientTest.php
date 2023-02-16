<?php

namespace BayWaReLusy\UsersAPI\Test;

use BayWaReLusy\UsersAPI\SDK\UserEntity;
use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;

class UsersApiClientTest extends TestCase
{
    protected UsersApiClient $instance;
    protected MockObject|CacheItemPoolInterface $cacheMock;
    protected MockObject|LoggerInterface $loggerMock;
    protected MockHandler $guzzleMockHandler;

    protected function setUp(): void
    {
        $this->cacheMock  = $this->createMock(CacheItemPoolInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->guzzleMockHandler = new MockHandler();
        $handlerStack            = HandlerStack::create($this->guzzleMockHandler);
        $client                  = new HttpClient(['handler' => $handlerStack]);

        $this->instance = new UsersApiClient(
            'my-api.baywa-lusy.com/users',
            'my-api.baywa-lusy.com/subsidiaries',
            'my-api.baywa-lusy.com/token',
            'client-id',
            'client-secret',
            $this->cacheMock,
            $client,
            $this->loggerMock
        );
    }

    public function testGetUsers_TokenHit(): void
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

        $this->cacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('usersApiAccessToken')
            ->will($this->returnValue($cacheItemMock));
        $this->cacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/users.json'))
        );

        // Execute the call
        $users = $this->instance->getUsers();

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
}
