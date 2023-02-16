<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Uri;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface as HttpClient;

class UsersApiClient
{
    protected ?string $accessToken = null;
    protected RequestFactory $requestFactory;

    public function __construct(
        protected string $usersApiUrl,
        protected string $subsidiariesApiUrl,
        protected string $tokenUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected CacheItemPoolInterface $cacheService,
        protected HttpClient $httpClient,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->requestFactory = new RequestFactory();
    }

    /**
     * @throws UsersApiException
     */
    protected function loginToAuthServer(): void
    {
        try {
            $cachedToken = $this->cacheService->getItem('usersApiAccessToken');

            // Get the Users API Access Token
            if ($cachedToken->isHit()) {
                $accessToken = $cachedToken->get();
            } else {
                $tokenRequest = $this->requestFactory->createRequest('POST', new Uri($this->tokenUrl));
                $tokenRequest->getBody()->write((string)json_encode([
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'state'         => time(),
                ]));

                $response    = $this->httpClient->sendRequest($tokenRequest);
                $body        = json_decode($response->getBody()->getContents(), true);
                $accessToken = $body['access_token'];

                $cachedToken
                    ->set($accessToken)
                    ->expiresAfter($body['expires_in'] - 10);

                $this->cacheService->save($cachedToken);
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
            $this->loginToAuthServer();
            $usersRequest = $this->requestFactory->createRequest('GET', new Uri($this->usersApiUrl));
            $usersRequest->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
            $response = $this->httpClient->sendRequest($usersRequest);
        } catch (\Throwable $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Users.");
        }

        $response = json_decode($response->getBody()->getContents(), true);
        $users    = [];

        foreach ($response['_embedded']['users'] as $userData) {
            $user = new UserEntity();
            $user
                ->setId($userData['id'])
                ->setUsername($userData['username'])
                ->setEmail($userData['email'])
                ->setEmailVerified($userData['emailVerified'])
                ->setCreated(\DateTime::createFromFormat(\DateTimeInterface::RFC3339, $userData['created']))
                ->setRoles($userData['roles']);

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Get the list of Subsidiaries.
     *
     * @return SubsidiaryEntity[]
     * @throws UsersApiException
     */
    public function getSubsidiaries(): array
    {
        try {
            $response = $this->getHttpClient()->get($this->subsidiariesApiUrl);
        } catch (\Throwable $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Subsidiaries.");
        }

        $response     = json_decode($response->getBody()->getContents(), true);
        $subsidiaries = [];

        foreach ($response['_embedded']['subsidiaries'] as $subsidiaryData) {
            $subsidiary = new SubsidiaryEntity();
            $subsidiary
                ->setId($subsidiaryData['id'])
                ->setName($subsidiaryData['name']);

            $subsidiaries[] = $subsidiary;
        }

        return $subsidiaries;
    }
}
