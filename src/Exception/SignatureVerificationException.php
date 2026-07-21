<?php

declare(strict_types=1);

namespace DPay\Exception;

use RuntimeException;

final class SignatureVerificationException extends RuntimeException implements ExceptionInterface
{
}
