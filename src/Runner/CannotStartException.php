<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Běh se nespustil vůbec — workflow neprošlo validací, nebo mu chybí
 * povinný vstup. Neproběhl ani jeden krok.
 *
 * Podtřída, ne samostatný typ, aby volající, kterého ten rozdíl nezajímá,
 * dál chytal jen RunFailedException.
 */
final class CannotStartException extends RunFailedException
{
}
