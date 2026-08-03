<?php

declare(strict_types=1);

namespace Donut\Cli;

use Donut\Exception;


/**
 * Volání z příkazové řádky nedává smysl — chybný tvar argumentu, neznámé
 * workflow, chybějící jméno. Běh se nespustí.
 */
final class UsageException extends Exception
{
}
