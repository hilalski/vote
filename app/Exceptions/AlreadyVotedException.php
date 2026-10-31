<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class AlreadyVotedException extends Exception implements ShouldntReport {}
