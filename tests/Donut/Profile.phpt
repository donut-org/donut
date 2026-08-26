<?php

declare(strict_types=1);

use Donut\Exception;
use Donut\Profile;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Default environment: profile `default` under ~/.config/donut.
$profile = Profile::fromEnvironment(['HOME' => '/home/x']);
Assert::same('default', $profile->name());
Assert::same('/home/x/.config/donut/default', $profile->dir());
Assert::same('/home/x/.config/donut/default/blocks', $profile->blocksDir());
Assert::same('/home/x/.config/donut/default/workflows', $profile->workflowsDir());

// XDG_CONFIG_HOME overrides HOME.
Assert::same(
	'/cfg/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => '/cfg'])->dir(),
);

// DONUT_HOME overrides both.
Assert::same(
	'/sets/default',
	Profile::fromEnvironment([
		'HOME' => '/home/x',
		'XDG_CONFIG_HOME' => '/cfg',
		'DONUT_HOME' => '/sets',
	])->dir(),
);

// DONUT_PROFILE picks the subdirectory and is also the profile's name.
$olw = Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'olw']);
Assert::same('olw', $olw->name());
Assert::same('/home/x/.config/donut/olw', $olw->dir());

// An empty value is the same as unset — the same rule as for workflow
// inputs. Without it `DONUT_PROFILE= donut …` would look in the profiles root.
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '', 'DONUT_HOME' => ''])->dir(),
);
Assert::same(
	'/home/x/.config/donut/default',
	Profile::fromEnvironment(['HOME' => '/home/x', 'XDG_CONFIG_HOME' => ''])->dir(),
);

// A relative DONUT_HOME is used as-is — no realpath() magic.
Assert::same('sets/default', Profile::fromEnvironment(['DONUT_HOME' => 'sets'])->dir());

// The profile name is a directory name, not a path. Paths elsewhere are done with a symlink.
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a/b']),
	Exception::class,
	'%A%Symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => '..']),
	Exception::class,
	'%A%Symlink%A%',
);
Assert::exception(
	fn() => Profile::fromEnvironment(['HOME' => '/home/x', 'DONUT_PROFILE' => 'a\\b']),
	Exception::class,
	'%A%Symlink%A%',
);

// An environment from which the path cannot be built must say how to fix it.
Assert::exception(
	fn() => Profile::fromEnvironment([]),
	Exception::class,
	'%A%DONUT_HOME%A%',
);
