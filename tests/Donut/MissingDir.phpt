<?php

declare(strict_types=1);

use Donut\MissingDir;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Rada musí být spustitelná tak, jak je: celá cesta a -p, protože chybět
// může i profil nad adresářem, ne jen adresář sám.
Assert::same(
	'Donut ho sám nezaloží — vytvoř ho příkazem `mkdir -p /home/x/.config/donut/default/blocks`.',
	MissingDir::hint('/home/x/.config/donut/default/blocks'),
);
