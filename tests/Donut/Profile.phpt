<?php

declare(strict_types=1);

use Donut\Exception;
use Donut\Profile;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Výchozí prostředí: profil `default` pod ~/.config/donut.
$profile = Profile::fromEnvironment(['HOME' => '/home/x']);
Assert::same('default', $profile->name());
Assert::same('/home/x/.config/donut/default', $profile->dir());
Assert::same('/home/x/.config/donut/default/blocks', $profile->blocksDir());
Assert::same('/home/x/.config/donut/default/workflows', $profile->workflowsDir());

// XDG_CONFIG_HOME přebíjí HOME.
Assert::same(
	'/cfg/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => '/cfg'])->dir(),
);

// DONUT_HOME přebíjí obojí.
Assert::same(
	'/sady/default',
	Profile::fromEnvironment([
		'HOME' => '/home/x',
		'XDG_CONFIG_HOME' => '/cfg',
		'DONUT_HOME' => '/sady',
	])->dir(),
);

// DONUT_PROFILE vybírá podadresář a je to i jméno profilu.
$olw = Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'olw']);
Assert::same('olw', $olw->name());
Assert::same('/home/x/.config/donut/olw', $olw->dir());

// Prázdná hodnota je totéž co nenastavená — stejné pravidlo jako u vstupů
// workflow. Bez něj by `DONUT_PROFILE= donut …` hledalo v kořeni profilů.
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '', 'DONUT_HOME' => ''])->dir(),
);
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => ''])->dir(),
);

// Relativní DONUT_HOME se použije, jak je — žádné realpath() kouzlení.
Assert::same('sady/default', Profile::fromEnvironment(['DONUT_HOME' => 'sady'])->dir());

// Jméno profilu je jméno adresáře, ne cesta. Cesty jinam se dělají symlinkem.
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a/b']),
	Exception::class,
	'%A%symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '..']),
	Exception::class,
	'%A%symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a\\b']),
	Exception::class,
	'%A%symlink%A%',
);

// Prostředí, ze kterého se cesta nedá složit, musí říct, čím to spravit.
Assert::exception(
	fn() => Profile::fromEnvironment([]),
	Exception::class,
	'%A%DONUT_HOME%A%',
);
