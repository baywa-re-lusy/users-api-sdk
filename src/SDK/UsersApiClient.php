<?php

namespace BayWaReLusy\UsersAPI\SDK;

use BayWaReLusy\UserManagement\UserEntity;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class UsersApiClient
{
    public function __construct(
        protected string $usersApiUrl,
        protected string $tokenUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected CacheItemPoolInterface $cacheService,
        protected ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return HttpClient
     * @throws UsersApiException
     */
    protected function getHttpClient(): HttpClient
    {
        try {
            $cachedToken = $this->cacheService->getItem('usersApiAccessToken');

            // Get the Users API Access Token
            if ($cachedToken->isHit()) {
                $accessToken = $cachedToken->get();
            } else {
                $tokenClient = new HttpClient();
                $response = $tokenClient->post(
                    $this->tokenUrl,
                    [
                        RequestOptions::FORM_PARAMS =>
                            [
                                'grant_type'    => 'client_credentials',
                                'client_id'     => $this->clientId,
                                'client_secret' => $this->clientSecret,
                                'state'         => time(),
                            ]
                    ]
                );

                $body = json_decode($response->getBody()->getContents(), true);
                $accessToken = $body['access_token'];
                $cachedToken->set($accessToken);
                $this->cacheService->save($cachedToken);
            }

            return new HttpClient(['headers' => [
                'Authorization' => sprintf("Bearer %s", $accessToken),
                'Accept'        => 'application/json',
            ]]);
        } catch (\Throwable $e) {
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
            $response = $this->getHttpClient()->get($this->usersApiUrl);
        } catch (\Throwable $e) {
            $this->logger?->error($e->getMessage());
            throw new UsersApiException("Couldn't retrieve the list of Users.");
        }

        $response = json_decode($response->getBody()->getContents(), true);
        $users    = [];

        foreach ($response as $userData) {
            error_log(var_export($userData, true));

            $user = new UserEntity();
//            $user
//                ->setId($userData['id'])
//                ->setUsername($userData['username'])
//                ->setEmailVerified($userData['emailVerified'])
//                ->setEmail($userData['email'])
//                ->setCreated($created);
////                ->setPicture($auth0User['picture'])
////                ->setLastUpdate($lastUpdate ?: null)
////                ->setLastLogin($lastLogin ?: null);
//
//            $this->getUserRoles($user);

            $users[] = $user;
        }

        return $users;
    }
}
