<?php

declare(strict_types=1);

namespace Donut\Runner;

use Donut\BlockRepository;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Validator\Validator;
use Nette\Utils\ProcessTimeoutException;


/**
 * Spouští workflow: prochází kroky, drží mapu, ukládá výstupy procesů.
 *
 * Validace běží uvnitř run(). Specifikace ji má jako záruku formátu, ne jako
 * službu volajícího — kdyby si ji měl volat sám, může na ni zapomenout CLI
 * i pozdější GUI.
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
	 * @return array<string, string> výsledná mapa
	 * @throws RunFailedException
	 */
	public function run(Workflow $workflow, array $initialMap = []): array
	{
		$result = $this->validator->validate($workflow);

		foreach ($result->getWarnings() as $warning) {
			$this->reporter->warning((string) $warning);
		}

		if ($result->hasErrors()) {
			$messages = \implode("\n", \array_map(strval(...), $result->getErrors()));

			throw new RunFailedException("Statická validace neprošla:\n{$messages}");
		}

		$map = $initialMap;
		$this->runSteps($workflow->steps, $workflow->name . '.json:steps', $map);

		return $map;
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

			if ($step instanceof RunStep) {
				$this->runCommand($step, $at, $map);

			} elseif ($step instanceof SetStep) {
				$this->reporter->step($at, $step->name ?? "set {$step->key}");
				$map[$step->key] = $step->value->render($map);

			} else {
				throw new RunFailedException("{$at}: krok typu " . \get_debug_type($step) . " runner neumí.");
			}
		}
	}


	/**
	 * @param  array<string, string> $map
	 * @throws RunFailedException
	 */
	private function runCommand(RunStep $step, string $at, array &$map): void
	{
		$block = $this->blocks->get($step->block);
		$this->reporter->step($at, $step->name ?? $block->name);

		$commandLine = CommandLine::build($block, $step->in, $map, $at);
		$stdin = isset($step->in['STDIN']) ? $step->in['STDIN']->render($map) : '';
		$captureStderr = isset($step->out['stderr']);
		$timeout = $step->timeout ?? $block->timeout ?? self::DefaultTimeout;

		try {
			$result = $this->processes->run(
				$commandLine->command,
				$commandLine->args,
				$stdin,
				$captureStderr,
				$timeout,
			);

		} catch (ProcessTimeoutException $e) {
			throw new RunFailedException(
				"{$at}: kámen \"{$block->name}\" překročil limit {$timeout} s.",
				0,
				$e,
			);
		}

		// Kanály se zapisují i u povoleného selhání — právě podle exit_code
		// se pak workflow rozhoduje v `if`.
		foreach ($step->out as $channel => $key) {
			$map[$key] = match ($channel) {
				'result' => $result->stdout,
				'stderr' => $result->stderr ?? '',
				'exit_code' => (string) $result->exitCode,
				default => throw new RunFailedException("{$at}: neznámý kanál \"{$channel}\"."),
			};
		}

		$allowFailure = $step->allowFailure ?? $block->allowFailure;

		if (!self::isAllowed($result->exitCode, $allowFailure)) {
			throw new RunFailedException(
				"{$at}: kámen \"{$block->name}\" skončil s exit code {$result->exitCode}."
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
