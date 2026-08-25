<?php
declare(strict_types=1);
namespace App\Util;
final class Token
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
