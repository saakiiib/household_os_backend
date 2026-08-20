<?php

namespace App\Database;

use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Support\Str;
use Throwable;

class CustomLostConnectionDetector extends LostConnectionDetector implements LostConnectionDetectorContract
{
    public function causedByLostConnection(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (Str::contains($message, [
            'Operation not permitted',
            'SQLSTATE[HY000] [2002]',
            '2002 Operation not permitted',
            'Resource temporarily unavailable',
            'Connection refused',
        ])) {
            return true;
        }

        return parent::causedByLostConnection($e);
    }
}
