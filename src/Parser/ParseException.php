<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Exception;


/**
 * The file cannot be loaded or does not match the format structure.
 *
 * Not final: NotFoundException narrows it to "there is no such file at all",
 * which the GUI answers with a 404 while a broken file stays a visible error.
 */
class ParseException extends Exception
{
}
