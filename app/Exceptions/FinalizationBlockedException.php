<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class FinalizationBlockedException extends Exception implements ShouldntReport {}
