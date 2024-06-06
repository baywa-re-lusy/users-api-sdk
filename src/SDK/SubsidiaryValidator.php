<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Validator\AbstractValidator;

class SubsidiaryValidator extends AbstractValidator
{
    protected const INVALID_SUBSIDIARY_ID = 'invalidSubsidiaryId';
    protected const SUBSIDIARY_NOT_FOUND  = 'subsidiaryNotFound';

    /**
     * Validation failure message template definitions
     *
     * @var array<string, string>
     */
    protected array $messageTemplates = [
        self::INVALID_SUBSIDIARY_ID => "Invalid Subsidiary ID",
        self::SUBSIDIARY_NOT_FOUND  => "Subsidiary not found",
    ];

    public function __construct(
        protected UsersApiClient $usersApiClient
    ) {
        parent::__construct();
    }

    public function isValid($value): bool
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            $this->error(self::INVALID_SUBSIDIARY_ID);
            return false;
        }

        if (!$this->usersApiClient->getSubsidiary($value)) {
            $this->error(self::SUBSIDIARY_NOT_FOUND);
            return false;
        }

        return true;
    }
}
