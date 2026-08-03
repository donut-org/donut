<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Exception;


/**
 * Běh workflow se zastavil — krok selhal, vypršel mu čas, nebo mu chybí
 * hodnota, bez které nejde spustit.
 */
final class RunFailedException extends Exception
{
}
