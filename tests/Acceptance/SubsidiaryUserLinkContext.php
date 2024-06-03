<?php

namespace BayWaReLusy\UsersAPI\Test\Acceptance;

use BayWaReLusy\UsersAPI\SDK\UserEntity;
use Behat\Behat\Context\Context;
use Psr\Cache\CacheItemPoolInterface;

class SubsidiaryUserLinkContext implements Context
{
    protected CacheItemPoolInterface $cache;

    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * @Given User :userId is linked to Subsidiary :subsidiaryId
     */
    public function userIsLinkedToSubsidiary($userId, $subsidiaryId)
    {
        // Find the user in the cache and add the Subsidiary to the allowed Subsidiaries list
        $cachedUser = $this->cache->getItem(sprintf('usersApiUser_%s', $userId));

        if ($cachedUser->isHit()) {
            /** @var UserEntity $user */
            $user            = $cachedUser->get();
            $subsidiaryIds   = $user->getSubsidiaryIds();
            $subsidiaryIds[] = $subsidiaryId;

            $user->setSubsidiaryIds($subsidiaryIds);

            return;
        }

        throw new \Exception(sprintf('User with id "%s" could not be found', $userId));
    }
}
