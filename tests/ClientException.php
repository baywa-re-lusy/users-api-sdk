<?php

namespace BayWaReLusy\UsersAPI\Test;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \Exception implements ClientExceptionInterface
{
}
