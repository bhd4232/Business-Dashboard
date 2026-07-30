<?php

namespace App\Services\CourierFraud;

interface CourierFraudClient
{
    /**
     * @param  array{username?: string, password?: string}  $credentials
     * @return array{success: int, cancel: int, total: int}|null null on failure/unconfigured
     */
    public function checkByPhone(string $phone, array $credentials): ?array;
}
