# Donut

[![Build Status](https://github.com/donut-org/donut/workflows/Build/badge.svg)](https://github.com/donut-org/donut/actions)
[![Downloads this Month](https://img.shields.io/packagist/dm/donut-org/donut.svg)](https://packagist.org/packages/donut-org/donut)
[![Latest Stable Version](https://poser.pugx.org/donut-org/donut/v/stable)](https://github.com/donut-org/donut/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/donut-org/donut/blob/master/license.md)

<a href="https://www.janpecha.cz/donate/"><img src="https://buymecoffee.intm.org/img/donate-banner.v1.svg" alt="Donate" height="100"></a>

Donut runs workflows described in JSON files. A workflow is a list of steps;
each step runs one unix command, and the steps pass values to each other
through a flat map of strings.

These workflows used to be bash scripts calling other bash scripts. Bash has
no way to say *this step needs these values* — a mistyped variable name is an
empty string, and an empty string is a perfectly good argument. Donut declares
inputs up front, checks before it starts that every value a step reads is
written by something earlier, and refuses to run a workflow that fails the
check.

**The documentation is in Czech**, and so are the messages Donut prints.
`docs/format-specifikace.md` is the reference for the format.


## Installation

[Download the latest package](https://github.com/donut-org/donut/releases) or use [Composer](http://getcomposer.org/):

```
composer require donut-org/donut
```

Donut requires PHP 8.1 or later.

Donut reads blocks and workflows from a profile, not from the current
directory. Create the default one:

```
mkdir -p ~/.config/donut/default/{blocks,workflows}
```

`DONUT_PROFILE=name` picks another profile, `DONUT_HOME=path` another root
of profiles. To use a set that lives elsewhere — in a project repository,
say — symlink it in: `ln -s ~/projects/olw/donut ~/.config/donut/olw`.


## Example

Donut looks for `blocks/` and `workflows/` **in the profile**, by default
`~/.config/donut/default/`:

```
~/.config/donut/default/
	blocks/
		greet.json
	workflows/
		hello.json
```

A *block* wraps one command and declares what it needs:

```json
{
	"name": "greet",
	"command": "echo",
	"args": [["Hello, {%name%}!"]],
	"inputs": {
		"name": { "required": true }
	}
}
```

A *workflow* is a list of steps, with its own inputs coming from the command
line:

```json
{
	"name": "hello",
	"description": "Greets someone.",
	"inputs": {
		"who": { "required": true, "description": "Whom to greet" }
	},
	"steps": [
		{
			"type": "run",
			"block": "greet",
			"in": { "name": "{%who%}" }
		}
	]
}
```

Run it. The first line is progress, written to stderr as each step runs;
the second is the step's own output:

```
$ vendor/bin/donut hello --who=world
hello.json:steps[0]  greet
Hello, world!
```

And ask it what it takes:

```
$ vendor/bin/donut --list
  hello        Greets someone.

$ vendor/bin/donut hello --help
hello — Greets someone.

Vstupy:
  --who=…          povinný   Whom to greet
```

Commands never go through a shell — Donut passes arguments to `execve` as a
list, so a value containing a space, a quote or a semicolon is just a value.

Exit codes: **0** the workflow finished, **1** a step failed while running,
**2** it never started (bad arguments, unknown workflow, failed validation).
The difference is there for automation: **2** means retrying is pointless.


## Documentation

In Czech:

| | |
|---|---|
| [`docs/format-specifikace.md`](docs/format-specifikace.md) | the format — blocks, workflows, the map, templates, validation |
| [`docs/zadani.md`](docs/zadani.md) | what this is for and what is left to build |
| [`docs/workflows/donut/`](docs/workflows/donut/) | a real workload — 15 blocks and 4 workflows |
| [`docs/superpowers/specs/`](docs/superpowers/specs/) | the design document behind each part |

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
