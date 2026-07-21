<?php

declare(strict_types=1);

namespace DPay\Payment;

use InvalidArgumentException;

final class ReturnUrls
{
    private string $success;

    private string $fail;

    private string $ipn;

    public function __construct(string $success, string $fail, string $ipn)
    {
        foreach (['success' => $success, 'fail' => $fail, 'ipn' => $ipn] as $name => $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException(sprintf('Invalid %s URL "%s"', $name, $url));
            }
        }
        $this->success = $success;
        $this->fail = $fail;
        $this->ipn = $ipn;
    }

    public function getSuccess(): string
    {
        return $this->success;
    }

    public function getFail(): string
    {
        return $this->fail;
    }

    public function getIpn(): string
    {
        return $this->ipn;
    }
}
