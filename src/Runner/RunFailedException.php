<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Exception;


/**
 * The workflow run has stopped — a step failed, timed out, or is missing a
 * value it cannot run without.
 *
 * Not final: CannotStartException is its subclass for the case where not a
 * single step ran.
 */
class RunFailedException extends Exception
{
}
