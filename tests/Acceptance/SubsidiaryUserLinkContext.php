<?php

namespace BayWaReLusy\UsersAPI\Test\Acceptance;

use BayWaReLusy\UsersAPI\SDK\UserEntity;
use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use Behat\Behat\Context\Context;
use Psr\Cache\CacheItemPoolInterface;

class SubsidiaryUserLinkContext implements Context
{
    protected CacheItemPoolInterface $cache;

    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function removeAllLinksToSubsidiaries(): void
    {
        $cachedUsers = $this->cache->getItem(UsersApiClient::CACHE_KEY_USERS);

        if ($cachedUsers->isHit()) {
            /** @var UserEntity $cachedUser */
            foreach ($cachedUsers->get() as $cachedUser) {
                // Remove subsidiaries from user in list
                $cachedUser->setSubsidiaryIds([]);

                // Get the single user from cache by ID and remove
                $cachedSingleUser = $this->cache->getItem(
                    sprintf(UsersApiClient::CACHE_KEY_USER, $cachedUser->getId())
                );

                if ($cachedSingleUser->isHit()) {
                    /** @var UserEntity $user */
                    $user = $cachedSingleUser->get();
                    $user->setSubsidiaryIds([]);
                    $this->cache->save($cachedSingleUser);
                }
            }

            $this->cache->save($cachedUsers);
        }
    }

    /**
     * @Given User :userId is linked to Subsidiary :subsidiaryId
     */
    public function userIsLinkedToSubsidiary(string $userId, string $subsidiaryId): void
    {
        // Find the user in the cache and add the Subsidiary to the allowed Subsidiaries list
        $cachedUser = $this->cache->getItem(sprintf(UsersApiClient::CACHE_KEY_USER, $userId));

        if ($cachedUser->isHit()) {
            /** @var UserEntity $user */
            $user            = $cachedUser->get();
            $subsidiaryIds   = $user->getSubsidiaryIds();
            $subsidiaryIds[] = $subsidiaryId;

            $user->setSubsidiaryIds(array_unique($subsidiaryIds));
            $this->cache->save($cachedUser);
        } else {
            $newUser = new UserEntity();
            $newUser
                ->setId($userId)
                ->setUsername($userId)
                ->setSubsidiaryIds([$subsidiaryId])
                ->setCreated(new \DateTime())
                ->setEmail($userId . '@baywa-re.com');

            $cachedUser->set($newUser);
            $this->cache->save($cachedUser);
        }

        // Find the user in the cached user list
        $cachedUsers = $this->cache->getItem(UsersApiClient::CACHE_KEY_USERS);

        if ($cachedUsers->isHit()) {
            foreach ($cachedUsers->get() as $user) {
                if ($user->getId() === $userId) {
                    $subsidiaryIds = $user->getSubsidiaryIds();
                    $subsidiaryIds[] = $subsidiaryId;
                    $user->setSubsidiaryIds(array_unique($subsidiaryIds));
                    $this->cache->save($cachedUsers);
                    break;
                }
            }
        }
    }
}
