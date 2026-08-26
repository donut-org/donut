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
 * Entry point from the command line.
 *
 * Takes the profile and streams in the constructor so it can be tested
 * without a real terminal — the same reason Reporter is an interface.
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
	 * @param string|null        $stdin     standard input content; null = read it itself
	 * @param ProcessRunner|null $processes null = NetteProcessRunner; any other value only in tests
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
	 * Entry point from bin/donut: assembles the profile from the environment
	 * and translates an environment error into a return code. It lives here,
	 * not in bin/donut, because a script that can't be run from a test must
	 * not contain any branch.
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
			\fwrite($stderr ?? STDERR, "Error: {$e->getMessage()}\n");

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

		// CannotStartException must come before RunFailedException — it's
		// its subclass and would otherwise be swallowed by the broader catch.
		} catch (UsageException | ParseException | CannotStartException $e) {
			\fwrite($this->stderr, "Error: {$e->getMessage()}\n");

			return self::NotStarted;

		} catch (RunFailedException $e) {
			\fwrite($this->stderr, "Error: {$e->getMessage()}\n");

			return self::Failed;

		// Code 2 even though a step never ran: of the meaning "didn't start",
		// the second half matters more here — don't repeat it. Retrying an
		// unclassified internal error is pointless. A distinct message says
		// the tool failed, not the workflow.
		} catch (DonutException $e) {
			\fwrite($this->stderr, "Internal tool error: {$e->getMessage()}\n");

			return self::NotStarted;
		}
	}


	private function listWorkflows(): int
	{
		$directory = $this->profile->workflowsDir();

		// An empty listing and a missing directory look the same on the
		// terminal — silence. The message has to say the difference, or a
		// fresh profile is a dead end. An exception instead of writing to
		// stderr directly, so the catch in run() attaches the prefix and
		// line ending the same as for other errors.
		if (!\is_dir($directory)) {
			throw new UsageException(
				"Workflows directory '{$directory}' does not exist. " . MissingDir::hint($directory)
			);
		}

		$code = self::Success;

		foreach ($this->workflowNames() as $name) {
			try {
				$workflow = $this->loadWorkflow($name);

			} catch (ParseException | UsageException $e) {
				// --list is a discovery command. One bad file must not hide
				// the rest, but it also must not leak into stdout, so the
				// listing can still be piped further.
				\fwrite($this->stderr, "Error: {$e->getMessage()}\n");
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
			\fwrite($this->stdout, "\nInputs:\n");

			foreach ($workflow->inputs as $name => $input) {
				\fwrite($this->stdout, \sprintf(
					"  --%-16s %-10s %s\n",
					$name . '=…',
					$input->required ? 'required' : 'optional',
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
					"Workflow \"{$workflow->name}\" has no input \"{$name}\"."
				);
			}
		}

		$blocksDir = $this->profile->blocksDir();

		// Same guard as in listWorkflows() for workflows/: BlockRepository
		// itself only reports "directory does not exist", without advice.
		// This is the only place runWorkflow() ever reaches a missing
		// blocks/ at all.
		if (!\is_dir($blocksDir)) {
			throw new UsageException(
				"Blocks directory '{$blocksDir}' does not exist. " . MissingDir::hint($blocksDir)
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
			// The `mkdir -p` advice only makes sense for a missing directory,
			// not for a typo in the workflow name.
			$hint = \is_dir($directory) ? '' : ' ' . MissingDir::hint($this->profile->workflowsDir());

			throw new UsageException(
				"Workflow \"{$name}\" does not exist. Searched in: {$directory}{$hint}"
			);
		}

		return (new WorkflowParser)->parseFile($path);
	}


	/**
	 * @return array<int, string> alphabetically
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
			donut --list                          list workflows
			donut <workflow> --help               help for a workflow
			donut <workflow> [--key=value …]      run

			Profile: {$this->profile->name()}  ({$this->profile->dir()})
			Other profile: DONUT_PROFILE=name, other root: DONUT_HOME=path

			TEXT);
	}
}
