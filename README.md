BayWa r.e. Users API SDK
========================

## Installation

```php
use Laminas\Cache\Storage\Adapter\Apcu;

$cacheItemPool = new \Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator(new Apcu());

$usersApiClient = new \BayWaReLusy\UsersAPI\SDK\UsersApiClient(
    "<URL to Users API>",
    "<URL to Token Endpoint>",
    "<Client ID>",
    "<Client Secret>",
    $cacheItemPool
);

$usersApiClient->getUsers();
```
