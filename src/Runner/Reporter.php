<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * Hlášení průběhu běhu.
 *
 * Rozhraní existuje proto, aby Runner nepsal na STDERR napřímo a šel
 * testovat. Produkční implementace je jedna, druhá je prázdná pro testy.
 */
interface Reporter
{
	/**
	 * @param string $path  cesta ke kroku ve tvaru steps[5].then[0]
	 * @param string $label jméno kroku, jméno kamene, nebo KLIC=hodnota u foreach
	 */
	public function step(string $path, string $label): void;

	public function warning(string $message): void;
}
