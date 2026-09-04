<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Contracts\Debug\ShouldntReport;

class RetryableBusinessException extends RuntimeException implements ShouldntReport
{
}
