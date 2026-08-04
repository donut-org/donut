<?php

declare(strict_types=1);

namespace Donut\Cli;

use Donut\BlockRepository;
use Donut\Exception as DonutException;
use Donut\Format\Workflow;
use Donut\Parser\ParseException;
use Donut\Parser\WorkflowParser;
use Donut\Runner\CannotStartException;
use Donut\Runner\ConsoleReporter;
use Donut\Runner\NetteProcessRunner;
use Donut\Runner\ProcessRunner;
use Donut\Runner\RunFailedException;
use Donut\Runner\Runner;


/**
 * Vstupní bod z příkazové řádky.
 *
 * Adresář a streamy bere v konstruktoru, aby šla testovat bez skutečného
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
		private readonly string $directory,
		$stdout = null,
		$stderr = null,
		private readonly ?string $stdin = null,
		private readonly ?ProcessRunner $processes = null,
	) {
		$this->stdout = $stdout ?? STDOUT;
		$this->stderr = $stderr ?? STDERR;
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

		$blocks = new BlockRepository($this->directory . '/blocks');
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
		$directory = $this->directory . '/workflows/';
		$path = $directory . $name . '.json';

		if (!\is_file($path)) {
			throw new UsageException(
				"Workflow \"{$name}\" neexistuje. Hledal jsem v: {$directory}"
			);
		}

		return (new WorkflowParser)->parseFile($path);
	}


	/**
	 * @return array<int, string> abecedně
	 */
	private function workflowNames(): array
	{
		$paths = \glob($this->directory . '/workflows/*.json');
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
		\fwrite($isError ? $this->stderr : $this->stdout, <<<'TEXT'
			donut --list                          seznam workflow
			donut <workflow> --help               nápověda k workflow
			donut <workflow> [--klic=hodnota …]   spuštění

			Kameny a workflow se hledají v ./blocks a ./workflows.

			TEXT);
	}
}
