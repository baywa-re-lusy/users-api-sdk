<?php

namespace BayWaReLusy\UsersAPI\Test\Console;

use BayWaReLusy\UsersAPI\SDK\Console\RefreshSubsidiaryCache;
use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshSubsidiaryCacheTest extends TestCase
{
    protected RefreshSubsidiaryCache $instance;
    protected MockObject $usersApiClientMock;
    protected MockObject $inputInterfaceMock;
    protected MockObject $outputInterfaceMock;

    protected function setUp(): void
    {
        $this->usersApiClientMock  = $this->createMock(UsersApiClient::class);
        $this->inputInterfaceMock  = $this->createMock(InputInterface::class);
        $this->outputInterfaceMock = $this->createMock(OutputInterface::class);

        $this->instance = new RefreshSubsidiaryCache($this->usersApiClientMock);
    }

    public function testExecute(): void
    {
        // The execute method is protected. Make it available through reflection
        $reflectionClass = new \ReflectionClass(RefreshSubsidiaryCache::class);
        $executeMethod   = $reflectionClass->getMethod('execute');
        $executeMethod->setAccessible(true);

        // Expect the setConsole() call
        $this->usersApiClientMock
            ->expects($this->once())
            ->method('setConsole')
            ->with($this->outputInterfaceMock)
            ->will($this->returnSelf());

        // Expect the getUsers() call
        $this->usersApiClientMock
            ->expects($this->once())
            ->method('getSubsidiaries')
            ->with(true);

        $result = $executeMethod->invoke($this->instance, $this->inputInterfaceMock, $this->outputInterfaceMock);

        $this->assertEquals(Command::SUCCESS, $result);
    }
}
