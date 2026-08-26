<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\BlockRepository;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Validator\Validator;
use Nette\Utils\ProcessFailedException;
use Nette\Utils\ProcessTimeoutException;


/**
 * Runs a workflow: walks the steps, holds the map, stores process outputs.
 *
 * Validation runs inside run(). The specification treats it as a format
 * guarantee, not a service for the caller — if the caller had to invoke it
 * themselves, both the CLI and the later GUI could forget to.
 */
final class Runner
{
	private const DefaultTimeout = 60;

	private readonly Validator $validator;


	public function __construct(
		private readonly BlockRepository $blocks,
		private readonly ProcessRunner $processes,
		private readonly Reporter $reporter,
	) {
		$this->validator = new Validator($blocks);
	}


	/**
	 * @param  array<string, string> $initialMap
	 * @return array<string, string> the resulting map
	 * @throws CannotStartException validation failed or a required input is missing — not a single step ran
	 * @throws RunFailedException
	 * @throws \Donut\Parser\ParseException a block in blocks/ cannot be parsed
	 */
	public function run(Workflow $workflow, array $initialMap = []): array
	{
		$result = $this->validator->validate($workflow);

		foreach ($result->getWarnings() as $warning) {
			$this->reporter->warning((string) $warning);
		}

		if ($result->hasErrors()) {
			$messages = \implode("\n", \array_map(strval(...), $result->getErrors()));

			throw new CannotStartException("Static validation failed:\n{$messages}");
		}

		$map = $this->composeInitialMap($workflow, $initialMap);
		$this->runSteps($workflow->steps, $workflow->name . '.json:steps', $map);

		return $map;
	}


	/**
	 * Fills into the caller-supplied map what the validator assumes as
	 * initial content: workflow input defaults, STDIN and CWD. Without this,
	 * a valid workflow with a default-valued input would die mid-run on a
	 * MissingKeyException with no step path.
	 *
	 * @param  array<string, string> $initialMap
	 * @return array<string, string>
	 * @throws RunFailedException
	 */
	private function composeInitialMap(Workflow $workflow, array $initialMap): array
	{
		$map = $initialMap;

		foreach ($workflow->inputs as $name => $input) {
			// An empty string is the same as unfilled — specification sections 1 and 4.
			// CommandLine::resolveValues() applies the same rule one layer down.
			if (($map[$name] ?? '') === '' && $input->default !== null) {
				$map[$name] = $input->default;
			}

			if (($map[$name] ?? '') === '' && $input->required) {
				throw new CannotStartException("{$workflow->name}.json: required input \"{$name}\" has no value.");
			}

			if (($map[$name] ?? '') === '' && !$input->required) {
				$map[$name] = '';
			}
		}

		$cwd = \getcwd();

		if ($cwd === false) {
			throw new CannotStartException("{$workflow->name}.json: cannot determine the current working directory.");
		}

		return $map + ['STDIN' => '', 'CWD' => $cwd];
	}


	/**
	 * @param  array<int, Step>      $steps
	 * @param  array<string, string> $map
	 * @throws RunFailedException
	 */
	private function runSteps(array $steps, string $path, array &$map): void
	{
		foreach ($steps as $i => $step) {
			$at = "{$path}[{$i}]";

			try {
				if ($step instanceof RunStep) {
					$this->runStep($step, $at, $map);

				} elseif ($step instanceof SetStep) {
					$this->reporter->step($at, $step->name ?? "set {$step->key}");
					$map[$step->key] = $step->value->render($map);

				} elseif ($step instanceof IfStep) {
					$this->reporter->step($at, $step->name ?? 'if');

					$matched = ConditionEvaluator::evaluate($step->condition, $map, $at);
					$branch = $matched ? $step->then : $step->else;

					$this->runSteps($branch, $at . ($matched ? '.then' : '.else'), $map);

				} elseif ($step instanceof ForeachStep) {
					$this->reporter->step($at, $step->name ?? 'foreach');

					foreach (self::splitLines($step->over->render($map)) as $line) {
						$map[$step->as] = $line;
						$this->reporter->step($at, "{$step->as}={$line}");
						$this->runSteps($step->steps, "{$at}.steps", $map);
					}

				} else {
					throw new RunFailedException("{$at}: the runner does not handle a step of type " . \get_debug_type($step) . ".");
				}

			} catch (\Donut\MissingKeyException $e) {
				// A nested runSteps() call has already wrapped MissingKeyException
				// in RunFailedException, so only the one from this step itself
				// reaches here — the path never gets rewritten a second time.
				throw new RunFailedException("{$at}: {$e->getMessage()}", 0, $e);
			}
		}
	}


	/**
	 * Splits the value into lines. A trailing \r on a line is stripped, empty
	 * lines are skipped, zero lines means zero iterations.
	 *
	 * @return array<int, string>
	 */
	private static function splitLines(string $value): array
	{
		$lines = [];

		foreach (\explode("\n", $value) as $line) {
			$line = \rtrim($line, "\r");

			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return $lines;
	}


	/**
	 * @param  array<string, string> $map
	 * @throws RunFailedException
	 */
	private function runStep(RunStep $step, string $at, array &$map): void
	{
		$block = $this->blocks->get($step->block);
		$this->reporter->step($at, $step->name ?? $block->name);

		$commandLine = CommandLine::build($block, $step->in, $map, $at);
		$stdin = isset($step->in['stdin']) ? $step->in['stdin']->render($map) : '';
		$captureStdout = isset($step->out['result']);
		$captureStderr = isset($step->out['stderr']);
		$timeout = $step->timeout ?? $block->timeout ?? self::DefaultTimeout;

		try {
			$result = $this->processes->run(
				$commandLine->command,
				$commandLine->args,
				$stdin,
				$captureStdout,
				$captureStderr,
				$timeout,
			);

		} catch (ProcessTimeoutException $e) {
			throw new RunFailedException(
				"{$at}: block \"{$block->name}\" exceeded the {$timeout} s limit.",
				0,
				$e,
			);

		} catch (ProcessFailedException $e) {
			throw new RunFailedException(
				"{$at}: block \"{$block->name}\" could not be started — command \"{$commandLine->command}\": {$e->getMessage()}",
				0,
				$e,
			);
		}

		// Channels are written even on an allowed failure — the workflow then
		// decides based on exit_code in `if`.
		foreach ($step->out as $channel => $key) {
			$map[$key] = match ($channel) {
				'result' => $result->stdout ?? '',
				'stderr' => $result->stderr ?? '',
				'exit_code' => (string) $result->exitCode,
				default => throw new RunFailedException("{$at}: unknown channel \"{$channel}\"."),
			};
		}

		$allowFailure = $step->allowFailure ?? $block->allowFailure;

		if (!self::isAllowed($result->exitCode, $allowFailure)) {
			throw new RunFailedException(
				"{$at}: block \"{$block->name}\" finished with exit code {$result->exitCode}."
			);
		}
	}


	/**
	 * @param bool|array<int, int> $allowFailure
	 */
	private static function isAllowed(int $exitCode, bool|array $allowFailure): bool
	{
		if ($allowFailure === true) {
			return true;
		}

		if (\is_array($allowFailure)) {
			return \in_array($exitCode, $allowFailure, true);
		}

		return $exitCode === 0;
	}
}
