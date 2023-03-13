<?php

namespace BayWaReLusy\UsersAPI\SDK;

use BayWaReLusy\JwtAuthentication\UserIdentity;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Uri;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface as HttpClient;

class UsersApiClient
{
    protected const CACHE_KEY_API_TOKEN            = 'usersApiAccessToken';
    protected const CACHE_KEY_USERS                = 'usersApiUsers';
    protected const CACHE_KEY_USER                 = 'usersApiUser_%s';
    protected const CACHE_KEY_SUBSIDIARIES         = 'usersApiSubsidiaries';
    protected const CACHE_KEY_SUBSIDIARIES_BY_USER = 'usersApiSubsidiaries_%s';
    protected const CACHE_TTL_USERS                = 600;
    protected const CACHE_TTL_SUBSIDIARIES         = 86400;
    protected const USERS_URI                      = '/users';
    protected const SUBSIDIARIES_URI               = '/subsidiaries';

    protected ?string $accessToken = null;
    protected RequestFactory $requestFactory;

    public function __construct(
        protected string $usersApiUrl,
        protected string $tokenUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected CacheItemPoolInterface $tokenCacheService,
        protected CacheItemPoolInterface $userCacheService,
        protected HttpClient $httpClient,
        protected ?LoggerInterface $logger = null
    ) {
        $this->requestFactory = new RequestFactory();
    }

    /**
     * Get a token for the Users API.
     *
     * @throws UsersApiException
     */
    protected function loginToAuthServer(): void
    {
        try {
            // Search for Users API token in Token Cache
            $cachedToken = $this->tokenCacheService->getItem(self::CACHE_KEY_API_TOKEN);

            // If the cached Token is valid
            if ($cachedToken->isHit()) {
                $accessToken = $cachedToken->get();
            } else {
                // If the cached Token isn't valid, generate a new one
                $tokenRequest = $this->requestFactory->createRequest('POST', new Uri($this->tokenUrl));
                $tokenRequest = $tokenRequest->withHeader('Accept', 'application/json');
                $tokenRequest = $tokenRequest->withHeader('Content-Type', 'application/x-www-form-urlencoded');

                $tokenRequest->getBody()->write(http_build_query([
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]));

                $response    = $this->httpClient->sendRequest($tokenRequest);
                $body        = json_decode($response->getBody()->getContents(), true);
                $accessToken = $body['access_token'];

                // Cache the new Token
                $cachedToken
                    ->set($accessToken)
                    ->expiresAfter($body['expires_in'] - 10);

                $this->tokenCacheService->save($cachedToken);
            }

            $this->accessToken = $accessToken;
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't connect to Users API.");
        }
    }

    /**
     * Get the list of Users.
     *
     * @return UserEntity[]
     * @throws UsersApiException
     */
    public function getUsers(): array
    {
        try {
            // Get the users from the cache
            $cachedUsers = $this->userCacheService->getItem(self::CACHE_KEY_USERS);

            // If the cached users are still valid, return them
            if ($cachedUsers->isHit()) {
                return $cachedUsers->get();
            }

            // If the cached users are no longer valid, get them from the Users API
            $this->loginToAuthServer();

            $request = $this->requestFactory->createRequest(
                'GET',
                new Uri(rtrim($this->usersApiUrl, '/') . self::USERS_URI)
            );
            $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
            $request = $request->withHeader('Accept', 'application/json');

            $response = $this->httpClient->sendRequest($request);

            $response = json_decode($response->getBody()->getContents(), true);
            $users    = [];

            foreach ($response['_embedded']['users'] as $userData) {
                $user = new UserEntity();
                $user
                    ->setId($userData['id'])
                    ->setUsername($userData['username'])
                    ->setEmail($userData['email'])
                    ->setEmailVerified($userData['emailVerified'])
                    ->setCreated(
                        \DateTime::createFromFormat(\DateTimeInterface::RFC3339, $userData['created']) ?: null
                    )
                    ->setRoles($userData['roles'])
                    ->setSubsidiaryIds($userData['subsidiaryIds']);

                $users[] = $user;
            }

            // Cache the Users
            $cachedUsers
                ->set($users)
                ->expiresAfter(self::CACHE_TTL_USERS);

            $this->userCacheService->save($cachedUsers);

            return $users;
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Users.");
        }
    }

    /**
     * Get a single User.
     *
     * @param string $id The User ID
     * @return UserEntity|null
     * @throws UsersApiException
     */
    public function getUser(string $id): ?UserEntity
    {
        try {
            // Get the users from the cache
            $cachedUser = $this->userCacheService->getItem(sprintf(self::CACHE_KEY_USER, $id));

            // If the cached user are still valid, return it
            if ($cachedUser->isHit()) {
                return $cachedUser->get();
            }

            // If the cached users are no longer valid, get them from the Users API
            $this->loginToAuthServer();

            $request = $this->requestFactory->createRequest(
                'GET',
                new Uri(rtrim($this->usersApiUrl, '/') . self::USERS_URI . '/' . $id)
            );
            $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
            $request = $request->withHeader('Accept', 'application/json');

            $response = $this->httpClient->sendRequest($request);
            $response = json_decode($response->getBody()->getContents(), true);

            $user = new UserEntity();
            $user
                ->setId($response['id'])
                ->setUsername($response['username'])
                ->setEmail($response['email'])
                ->setEmailVerified($response['emailVerified'])
                ->setCreated(
                    \DateTime::createFromFormat(\DateTimeInterface::RFC3339, $response['created']) ?: null
                )
                ->setRoles($response['roles'])
                ->setSubsidiaryIds($response['subsidiaryIds']);


            // Cache the Users
            $cachedUser
                ->set($user)
                ->expiresAfter(self::CACHE_TTL_USERS);

            $this->userCacheService->save($cachedUser);

            return $user;
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Users.");
        }
    }

    /**
     * Get the list of Subsidiaries, optionally filtered by User.
     *
     * @return SubsidiaryEntity[]
     * @throws UsersApiException
     */
    public function getSubsidiaries(): array
    {
        try {
            $cacheKey = self::CACHE_KEY_SUBSIDIARIES;

            // Get the subsidiaries from the cache
            $cachedSubsidiaries = $this->userCacheService->getItem($cacheKey);

            // If the cached users are still valid, return them
            if ($cachedSubsidiaries->isHit()) {
                return $cachedSubsidiaries->get();
            }

            $this->loginToAuthServer();

            $url = rtrim($this->usersApiUrl, '/') . self::SUBSIDIARIES_URI;

            $request = $this->requestFactory->createRequest('GET', new Uri($url));
            $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
            $request = $request->withHeader('Accept', 'application/json');

            $response = $this->httpClient->sendRequest($request);

            $response     = json_decode($response->getBody()->getContents(), true);
            $subsidiaries = [];

            foreach ($response['_embedded']['subsidiaries'] as $subsidiaryData) {
                $subsidiary = new SubsidiaryEntity();
                $subsidiary
                    ->setId($subsidiaryData['id'])
                    ->setName($subsidiaryData['name']);

                $subsidiaries[] = $subsidiary;
            }

            // Cache the Subsidiaries. If it's a users Subsidiaries, the TTL is shorter
            $cachedSubsidiaries
                ->set($subsidiaries)
                ->expiresAfter(self::CACHE_TTL_SUBSIDIARIES);

            $this->userCacheService->save($cachedSubsidiaries);

            return $subsidiaries;
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Subsidiaries.");
        }
    }
}
