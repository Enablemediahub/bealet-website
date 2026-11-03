<?php

namespace App\Support;

class Money
{
    public static function toPesewas(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function formatGhs(int $pesewas): string
    {
        return 'GH₵ '.number_format($pesewas / 100, 2);
    }
}
