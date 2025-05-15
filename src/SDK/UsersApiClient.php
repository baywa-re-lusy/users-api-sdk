<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Uri;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface as HttpClient;
use Symfony\Component\Console\Output\OutputInterface as Console;

class UsersApiClient
{
    public const CACHE_KEY_USERS           = 'usersApiUsers';
    public const CACHE_KEY_USER            = 'usersApiUser_%s';
    protected const CACHE_KEY_API_TOKEN    = 'usersApiAccessToken';
    protected const CACHE_KEY_SUBSIDIARIES = 'usersApiSubsidiaries';
    protected const CACHE_KEY_SUBSIDIARY   = 'usersApiSubsidiary_%s';
    protected const CACHE_TTL_USERS        = 0;
    protected const CACHE_TTL_SUBSIDIARIES = 0;
    protected const USERS_URI              = '/users';
    protected const SUBSIDIARIES_URI       = '/subsidiaries';

    protected ?string $accessToken = null;
    protected RequestFactory $requestFactory;
    protected ?Console $console = null;

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
     * @param Console $console
     * @return UsersApiClient
     */
    public function setConsole(Console $console): UsersApiClient
    {
        $this->console = $console;
        return $this;
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
     * @param bool $refreshCache If true, users are fetched from the API and the cache is refreshed
     * @return UserEntity[]
     * @throws UsersApiException
     */
    public function getUsers(bool $refreshCache = false): array
    {
        try {
            $this->console?->writeln(sprintf(
                "[%s] Fetching users from Users API...",
                (new \DateTime())->format(\DateTimeInterface::RFC3339)
            ));

            // Get the users from the cache
            $cachedUsers = $this->userCacheService->getItem(self::CACHE_KEY_USERS);

            // If the cached users are still valid and if there is no forced refresh, return them
            if (!$refreshCache && $cachedUsers->isHit()) {
                $cacheResult = $cachedUsers->get();

                $this->console?->writeln(sprintf(
                    "[%s] Fetched %s users from cache.",
                    (new \DateTime())->format(\DateTimeInterface::RFC3339),
                    count($cacheResult)
                ));

                return $cacheResult;
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

            // Loop over the result from the API and create User entities
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

                // Add user to cache
                $cachedUser = $this->userCacheService->getItem(sprintf(self::CACHE_KEY_USER, $userData['id']));
                $cachedUser
                    ->expiresAfter(self::CACHE_TTL_USERS)
                    ->set($user);

                $this->userCacheService->save($cachedUser);

                $this->console?->writeln(sprintf(
                    "[%s] Cached User '%s'.",
                    (new \DateTime())->format(\DateTimeInterface::RFC3339),
                    $user->getUsername()
                ));
            }

            // Cache the list of Users
            $cachedUsers
                ->set($users)
                ->expiresAfter(self::CACHE_TTL_USERS);

            $this->userCacheService->save($cachedUsers);

            $this->console?->writeln(sprintf(
                "[%s] Cached the User list, containing %s users.",
                (new \DateTime())->format(\DateTimeInterface::RFC3339),
                count($users)
            ));

            $this->console?->writeln(sprintf(
                "[%s] Fetched & cached %s users from API.",
                (new \DateTime())->format(\DateTimeInterface::RFC3339),
                count($users)
            ));

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

            // If the cached user is still valid, return it
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

            // Check for errors
            if ($response->getStatusCode() >= 400) {
                if ($response->getStatusCode() === 404) {
                    return null;
                }

                throw new \Exception(sprintf("Received status code %s from Users API.", $response->getStatusCode()));
            }

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
     * Get a single Subsidiary.
     *
     * @param string $subsidiaryId
     * @return SubsidiaryEntity|null
     * @throws UsersApiException
     * @throws ClientExceptionInterface
     */
    public function getSubsidiary(string $subsidiaryId): ?SubsidiaryEntity
    {
        try {
            $cachedSubsidiay = $this->userCacheService->getItem(
                sprintf(self::CACHE_KEY_SUBSIDIARY, $subsidiaryId)
            );
        } catch (InvalidArgumentException $e) {
            throw new UsersApiException('Invalid Subsidiary ID');
        }

        if ($cachedSubsidiay->isHit()) {
            return $cachedSubsidiay->get();
        }

        foreach ($this->fetchSubsidiariesFromApi() as $subsidiary) {
            if ($subsidiary->getId() === $subsidiaryId) {
                return $subsidiary;
            }
        }

        return null;
    }

    /**
     * Get the list of Subsidiaries, optionally filtered by User.
     *
     * @param bool $refreshCache If true, users are fetched from the API and the cache is refreshed
     * @return SubsidiaryEntity[]
     * @throws UsersApiException
     */
    public function getSubsidiaries(bool $refreshCache = false, ?UserEntity $user = null): array
    {
        try {
            $this->console?->writeln(sprintf(
                "[%s] Fetching subsidiaries from Users API...",
                (new \DateTime())->format(\DateTimeInterface::RFC3339)
            ));

            // Get the Cache item
            $cacheKey           = self::CACHE_KEY_SUBSIDIARIES;
            $cachedSubsidiaries = $this->userCacheService->getItem($cacheKey);

            // Get Subsidiaries from Cache
            if (!$refreshCache) {
                // Cache hit
                if ($cachedSubsidiaries->isHit()) {
                    /** @var SubsidiaryEntity[] $cacheResult */
                    $cacheResult = $cachedSubsidiaries->get();

                    $this->console?->writeln(sprintf(
                        "[%s] Fetched %s subsidiaries from cache.",
                        (new \DateTime())->format(\DateTimeInterface::RFC3339),
                        count($cacheResult)
                    ));

                    return is_null($user) ?
                        $cacheResult :
                        $this->filterSubsidiariesByUser($cacheResult, $user);
                }
            }

            // Cache miss
            $subsidiaries = $this->fetchSubsidiariesFromApi();

            // Check if the result must be filtered by user
            return is_null($user) ?
                $subsidiaries :
                $this->filterSubsidiariesByUser($subsidiaries, $user);
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Subsidiaries.");
        }
    }

    /**
     * @return SubsidiaryEntity[]
     * @throws UsersApiException
     * @throws ClientExceptionInterface
     */
    protected function fetchSubsidiariesFromApi(): array
    {
        $this->loginToAuthServer();

        $url = rtrim($this->usersApiUrl, '/') . self::SUBSIDIARIES_URI;

        $request = $this->requestFactory->createRequest('GET', new Uri($url));
        $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
        $request = $request->withHeader('Accept', 'application/json');

        $response = $this->httpClient->sendRequest($request);

        $response     = json_decode($response->getBody()->getContents(), true);
        $subsidiaries = [];
        $hydrator     = new SubsidiaryHydrator();

        foreach ($response['_embedded']['subsidiaries'] as $subsidiaryData) {
            $subsidiaries[] = $hydrator->hydrate($subsidiaryData, new SubsidiaryEntity());
        }

        // Cache the Subsidiaries
        $cachedSubsidiaries = $this->userCacheService->getItem(self::CACHE_KEY_SUBSIDIARIES);
        $cachedSubsidiaries
            ->set($subsidiaries)
            ->expiresAfter(self::CACHE_TTL_SUBSIDIARIES);

        $this->userCacheService->save($cachedSubsidiaries);

        // Cache each subsidiary individually
        foreach ($subsidiaries as $subsidiary) {
            $cachedSubsidiary = $this->userCacheService->getItem(
                sprintf(self::CACHE_KEY_SUBSIDIARY, $subsidiary->getId())
            );

            $cachedSubsidiary
                ->set($subsidiary)
                ->expiresAfter(self::CACHE_TTL_SUBSIDIARIES);

            $this->userCacheService->save($cachedSubsidiary);

            $this->console?->writeln(sprintf(
                "[%s] Cached Subsidiary '%s'.",
                (new \DateTime())->format(\DateTimeInterface::RFC3339),
                $subsidiary->getName()
            ));
        }

        $this->console?->writeln(sprintf(
            "[%s] Fetched %s subsidiaries from API.",
            (new \DateTime())->format(\DateTimeInterface::RFC3339),
            count($subsidiaries)
        ));

        return $subsidiaries;
    }

    /**
     * @param SubsidiaryEntity[] $subsidiaries
     * @param UserEntity $user
     * @return SubsidiaryEntity[]
     */
    protected function filterSubsidiariesByUser(array $subsidiaries, UserEntity $user): array
    {
        return array_filter($subsidiaries, function (SubsidiaryEntity $subsidiary) use ($user) {
            return in_array($subsidiary->getId(), $user->getSubsidiaryIds());
        });
    }
}
