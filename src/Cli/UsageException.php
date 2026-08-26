<?php

declare(strict_types=1);

namespace Donut\Cli;

use Donut\Exception;


/**
 * The command-line call doesn't make sense — a malformed argument, an
 * unknown workflow, a missing name. The run doesn't start.
 */
final class UsageException extends Exception
{
}
