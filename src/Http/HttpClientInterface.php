<?php

declare(strict_types=1);

namespace DPay\Http;

interface HttpClientInterface
{
    public function request(ApiRequest $request): ApiResponse;
}
