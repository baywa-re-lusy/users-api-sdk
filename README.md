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
