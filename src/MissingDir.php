<?php

declare(strict_types=1);

namespace Donut;


/**
 * Co s chybějícím adresářem `workflows/` nebo `blocks/`.
 *
 * Donut adresáře **nezakládá**: mlčky sypat adresáře na disk je horší než
 * hláška. Hláška tedy musí říct, co udělat — jinak je čerstvý profil slepá
 * ulička. Celá cesta a `-p` proto, že chybět může i profil nad adresářem.
 */
final class MissingDir
{
	public static function hint(string $directory): string
	{
		return 'Donut ho sám nezaloží — vytvoř ho příkazem `mkdir -p ' . $directory . '`.';
	}
}
