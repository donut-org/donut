<?php

declare(strict_types=1);

namespace Donut\Cli;

use Donut\BlockRepository;
use Donut\Exception as DonutException;
use Donut\Format\Workflow;
use Donut\MissingDir;
use Donut\Parser\ParseException;
use Donut\Parser\WorkflowParser;
use Donut\Profile;
use Donut\Runner\CannotStartException;
use Donut\Runner\ConsoleReporter;
use Donut\Runner\NetteProcessRunner;
use Donut\Runner\ProcessRunner;
use Donut\Runner\RunFailedException;
use Donut\Runner\Runner;


/**
 * Vstupní bod z příkazové řádky.
 *
 * Profil a streamy bere v konstruktoru, aby šla testovat bez skutečného
 * terminálu — stejný důvod, proč má Reporter rozhraní.
 */
final class Application
{
	public const Success = 0;
	public const Failed = 1;
	public const NotStarted = 2;

	/** @var resource */
	private $stdout;

	/** @var resource */
	private $stderr;


	/**
	 * @param resource|null      $stdout
	 * @param resource|null      $stderr
	 * @param string|null        $stdin     obsah standardního vstupu; null = přečíst si ho sám
	 * @param ProcessRunner|null $processes null = NetteProcessRunner; jiná hodnota jen v testech
	 */
	public function __construct(
		private readonly Profile $profile,
		$stdout = null,
		$stderr = null,
		private readonly ?string $stdin = null,
		private readonly ?ProcessRunner $processes = null,
	) {
		$this->stdout = $stdout ?? STDOUT;
		$this->stderr = $stderr ?? STDERR;
	}


	/**
	 * Vstupní bod z bin/donut: složí profil z prostředí a chybu prostředí
	 * přeloží na návratový kód. Je to tady, a ne v bin/donut, protože ve
	 * skriptu, který se nedá spustit z testu, nesmí zůstat žádná větev.
	 *
	 * @param array<int, string>    $argv
	 * @param array<string, string> $env
	 * @param resource|null         $stdout
	 * @param resource|null         $stderr
	 */
	public static function main(array $argv, array $env, $stdout = null, $stderr = null): int
	{
		try {
			$profile = Profile::fromEnvironment($env);

		} catch (DonutException $e) {
			\fwrite($stderr ?? STDERR, "Chyba: {$e->getMessage()}\n");

			return self::NotStarted;
		}

		return (new self($profile, $stdout, $stderr))->run($argv);
	}


	/**
	 * @param array<int, string> $argv
	 */
	public function run(array $argv): int
	{
		try {
			$arguments = Arguments::parse($argv);

			if ($arguments->list) {
				return $this->listWorkflows();
			}

			if ($arguments->workflow === null) {
				$this->printUsage(isError: !$arguments->help);

				return $arguments->help ? self::Success : self::NotStarted;
			}

			$workflow = $this->loadWorkflow($arguments->workflow);

			if ($arguments->help) {
				return $this->printWorkflowHelp($workflow);
			}

			return $this->runWorkflow($workflow, $arguments->values);

		// CannotStartException musí být před RunFailedException — je to
		// jeho podtřída a jinak by ji pohltil obecnější catch.
		} catch (UsageException | ParseException | CannotStartException $e) {
			\fwrite($this->stderr, "Chyba: {$e->getMessage()}\n");

			return self::NotStarted;

		} catch (RunFailedException $e) {
			\fwrite($this->stderr, "Chyba: {$e->getMessage()}\n");

			return self::Failed;

		// Kód 2, i když neproběhl krok: z významu „nespustilo se" je tady
		// podstatnější druhá polovina — neopakuj to. Opakovat neklasifikovanou
		// vnitřní chybu je marné. Odlišená hláška říká, že selhal nástroj,
		// ne workflow.
		} catch (DonutException $e) {
			\fwrite($this->stderr, "Vnitřní chyba nástroje: {$e->getMessage()}\n");

			return self::NotStarted;
		}
	}


	private function listWorkflows(): int
	{
		$directory = $this->profile->workflowsDir();

		// Prázdný výpis a chybějící adresář vypadají na terminálu stejně —
		// jako ticho. Rozdíl musí říct hláška, jinak je čerstvý profil slepá
		// ulička. Výjimka místo přímého zápisu na stderr, ať prefix i konec
		// řádku přilepí catch v run() stejně jako u ostatních chyb.
		if (!\is_dir($directory)) {
			throw new UsageException(
				"Adresář s workflow '{$directory}' neexistuje. " . MissingDir::hint($directory)
			);
		}

		$code = self::Success;

		foreach ($this->workflowNames() as $name) {
			try {
				$workflow = $this->loadWorkflow($name);

			} catch (ParseException | UsageException $e) {
				// --list je poznávací příkaz. Jeden vadný soubor nesmí schovat
				// ostatní, ale nesmí ani protéct do stdout, aby se výpis dal
				// dál zpracovat.
				\fwrite($this->stderr, "Chyba: {$e->getMessage()}\n");
				$code = self::NotStarted;

				continue;
			}

			\fwrite($this->stdout, \sprintf("  %-12s %s\n", $name, $workflow->description ?? ''));
		}

		return $code;
	}


	private function printWorkflowHelp(Workflow $workflow): int
	{
		\fwrite($this->stdout, "{$workflow->name} — " . ($workflow->description ?? '') . "\n");

		if ($workflow->inputs !== []) {
			\fwrite($this->stdout, "\nVstupy:\n");

			foreach ($workflow->inputs as $name => $input) {
				\fwrite($this->stdout, \sprintf(
					"  --%-16s %-10s %s\n",
					$name . '=…',
					$input->required ? 'povinný' : 'volitelný',
					$input->description ?? '',
				));
			}
		}

		return self::Success;
	}


	/**
	 * @param array<string, string> $values
	 */
	private function runWorkflow(Workflow $workflow, array $values): int
	{
		foreach (\array_keys($values) as $name) {
			if (!isset($workflow->inputs[$name])) {
				throw new UsageException(
					"Workflow \"{$workflow->name}\" nezná vstup \"{$name}\"."
				);
			}
		}

		$blocksDir = $this->profile->blocksDir();

		// Stejný guard jako v listWorkflows() pro workflows/: BlockRepository
		// sama hlásí jen "adresář neexistuje", bez rady. Tady je to jediné
		// místo, kudy runWorkflow() k chybějícímu blocks/ vůbec dojde.
		if (!\is_dir($blocksDir)) {
			throw new UsageException(
				"Adresář s kameny '{$blocksDir}' neexistuje. " . MissingDir::hint($blocksDir)
			);
		}

		$blocks = new BlockRepository($blocksDir);
		$runner = new Runner(
			$blocks,
			$this->processes ?? new NetteProcessRunner,
			new ConsoleReporter($this->stderr),
		);

		$runner->run($workflow, $values + ['STDIN' => $this->readStdin()]);

		return self::Success;
	}


	private function loadWorkflow(string $name): Workflow
	{
		$directory = $this->profile->workflowsDir() . '/';
		$path = $directory . $name . '.json';

		if (!\is_file($path)) {
			// Rada `mkdir -p` dává smysl jen u chybějícího adresáře, ne
			// u překlepu ve jméně workflow.
			$hint = \is_dir($directory) ? '' : ' ' . MissingDir::hint($this->profile->workflowsDir());

			throw new UsageException(
				"Workflow \"{$name}\" neexistuje. Hledal jsem v: {$directory}{$hint}"
			);
		}

		return (new WorkflowParser)->parseFile($path);
	}


	/**
	 * @return array<int, string> abecedně
	 */
	private function workflowNames(): array
	{
		$paths = \glob($this->profile->workflowsDir() . '/*.json');
		$names = [];

		foreach ($paths === false ? [] : $paths as $path) {
			$names[] = \basename($path, '.json');
		}

		\sort($names);

		return $names;
	}


	private function readStdin(): string
	{
		if ($this->stdin !== null) {
			return $this->stdin;
		}

		if (\stream_isatty(STDIN)) {
			return '';
		}

		return (string) \stream_get_contents(STDIN);
	}


	private function printUsage(bool $isError): void
	{
		\fwrite($isError ? $this->stderr : $this->stdout, <<<TEXT
			donut --list                          seznam workflow
			donut <workflow> --help               nápověda k workflow
			donut <workflow> [--klic=hodnota …]   spuštění

			Profil: {$this->profile->name()}  ({$this->profile->dir()})
			Jiný profil: DONUT_PROFILE=jmeno, jiný kořen: DONUT_HOME=cesta

			TEXT);
	}
}
