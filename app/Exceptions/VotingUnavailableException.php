<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class VotingUnavailableException extends Exception implements ShouldntReport {}
