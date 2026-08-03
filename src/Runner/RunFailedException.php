<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\Exception;


/**
 * Běh workflow se zastavil — krok selhal, vypršel mu čas, nebo mu chybí
 * hodnota, bez které nejde spustit.
 *
 * Ne final: CannotStartException je její podtřída pro případ, že se
 * neproběhl ani jeden krok.
 */
class RunFailedException extends Exception
{
}
