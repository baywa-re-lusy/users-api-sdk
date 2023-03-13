BayWa r.e. Users API SDK
========================

This SDK can be used to retrieve Users and Subsidiaries.

All dependencies injected into the constructor are PSR-compatible:
* Cache : https://www.php-fig.org/psr/psr-6/
* HTTP Client : https://www.php-fig.org/psr/psr-18/
* HTTP Messages : https://www.php-fig.org/psr/psr-7/
* Logger : https://www.php-fig.org/psr/psr-3/

## Installation

```shell
composer require baywa-re-lusy/users-api-sdk
```

## Usage

```php
use Laminas\Cache\Storage\Adapter\Apcu;

$tokenCache  = new \Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator(new Apcu());
$resultCache = new \Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator(new Apcu());
$httpClient  = new \GuzzleHttp\Client();

$usersApiClient = new \BayWaReLusy\UsersAPI\SDK\UsersApiClient(
    "<URL to Users API>",
    "<URL to Token API Endpoint>",
    "<Client ID>",
    "<Client Secret>",
    $tokenCache,
    $resultCache,
    $httpClient    
);

$users            = $usersApiClient->getUsers();
$subsidiaries     = $usersApiClient->getSubsidiaries();
$user             = $usersApiClient->getUser('<userId>');
```

## Cache Refresh via Console commands

This SDK contains Symfony Console commands to refresh the User/Subsidiary cache. You can include the Console commands
into your application:

```php
$cliApp = new \Symfony\Component\Console\Application();

$cliApp->add(new \BayWaReLusy\UsersAPI\SDK\Console\RefreshUserCache($usersApiClient));
$cliApp->add(new \BayWaReLusy\UsersAPI\SDK\Console\RefreshSubsidiaryCache($usersApiClient)));
```

And then run the Console commands with:

```shell
./console users-api-sdk:refresh-user-cache
./console users-api-sdk:refresh-subsidiary-cache
```
