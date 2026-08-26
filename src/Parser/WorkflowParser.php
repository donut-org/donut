<?php

declare(strict_types=1);

namespace Donut\Parser;

use Donut\Format\Condition;
use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;


/**
 * JSON file from workflows/ into a Workflow object.
 *
 * Checks only the structure of a single file — that steps have the required keys
 * of the right types. Block existence and key flow are handled by the validator.
 */
final class WorkflowParser
{
	/**
	 * @throws ParseException
	 */
	public function parseFile(string $path): Workflow
	{
		$workflow = $this->parseArray(JsonSource::readFile($path), $path);
		$expected = \basename($path, '.json');

		if ($workflow->name !== $expected) {
			throw new ParseException(
				"{$path}: name '{$workflow->name}' does not match the file name '{$expected}'."
			);
		}

		return $workflow;
	}


	/**
	 * @param  array<mixed> $data
	 * @throws ParseException
	 */
	public function parseArray(array $data, string $location): Workflow
	{
		JsonSource::rejectUnknownKeys($data, ['name', 'description', 'inputs', 'steps'], $location, '');

		if (!isset($data['name']) || !\is_string($data['name']) || $data['name'] === '') {
			throw new ParseException("{$location}: key 'name' is required and must be a non-empty string.");
		}

		if (!isset($data['steps']) || !\is_array($data['steps'])) {
			throw new ParseException("{$location}: key 'steps' is required and must be an array.");
		}

		return new Workflow(
			name: $data['name'],
			inputs: JsonSource::parseInputs($data, $location),
			steps: $this->parseSteps($data['steps'], $location, 'steps'),
			description: JsonSource::optionalString($data, 'description', $location, 'description'),
		);
	}


	/**
	 * @param  array<mixed> $steps
	 * @return array<int, Step>
	 * @throws ParseException
	 */
	private function parseSteps(array $steps, string $location, string $path): array
	{
		$result = [];

		foreach ($steps as $i => $step) {
			if (!\is_array($step)) {
				throw new ParseException("{$location}: {$path}[{$i}] must be an object.");
			}

			$result[] = $this->parseStep($step, $location, "{$path}[{$i}]");
		}

		return $result;
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseStep(array $step, string $location, string $path): Step
	{
		$type = $step['type'] ?? null;

		if (!\is_string($type)) {
			throw new ParseException("{$location}: {$path} has no 'type' key.");
		}

		$name = JsonSource::optionalString($step, 'name', $location, "{$path}.name");

		return match ($type) {
			'run' => $this->parseRun($step, $location, $path, $name),
			'if' => $this->parseIf($step, $location, $path, $name),
			'set' => $this->parseSet($step, $location, $path, $name),
			'foreach' => $this->parseForeach($step, $location, $path, $name),
			default => throw new ParseException("{$location}: {$path} has an unknown step type '{$type}'."),
		};
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseRun(array $step, string $location, string $path, ?string $name): RunStep
	{
		JsonSource::rejectUnknownKeys(
			$step,
			['type', 'block', 'in', 'out', 'name', 'allow_failure', 'timeout'],
			$location,
			$path,
		);

		if (!isset($step['block']) || !\is_string($step['block'])) {
			throw new ParseException("{$location}: {$path} has no 'block' key.");
		}

		$in = [];

		foreach ($this->objectOrEmpty($step, 'in', $location, $path) as $key => $value) {
			if (!\is_string($key) || !\is_string($value)) {
				throw new ParseException("{$location}: {$path}.in must be an object of string => string.");
			}

			$in[$key] = Template::parse($value);
		}

		$out = [];

		foreach ($this->objectOrEmpty($step, 'out', $location, $path) as $channel => $key) {
			if (!\is_string($channel) || !\is_string($key)) {
				throw new ParseException("{$location}: {$path}.out must be an object of string => string.");
			}

			$out[$channel] = $key;
		}

		$allowFailure = isset($step['allow_failure'])
			? JsonSource::parseAllowFailure($step['allow_failure'], $location, "{$path}.allow_failure")
			: null;

		$timeout = null;

		if (isset($step['timeout'])) {
			if (!\is_int($step['timeout']) || $step['timeout'] < 1) {
				throw new ParseException("{$location}: {$path}.timeout must be a positive integer.");
			}

			$timeout = $step['timeout'];
		}

		return new RunStep(
			block: $step['block'],
			in: $in,
			out: $out,
			timeout: $timeout,
			allowFailure: $allowFailure,
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseIf(array $step, string $location, string $path, ?string $name): IfStep
	{
		JsonSource::rejectUnknownKeys(
			$step,
			['type', 'condition', 'then', 'else', 'name'],
			$location,
			$path,
		);

		if (!isset($step['condition']) || !\is_array($step['condition'])) {
			throw new ParseException("{$location}: {$path} has no 'condition' key.");
		}

		$condition = $step['condition'];

		JsonSource::rejectUnknownKeys($condition, ['left', 'op', 'right'], $location, "{$path}.condition");

		if (!isset($condition['left']) || !\is_string($condition['left'])) {
			throw new ParseException("{$location}: {$path}.condition has no 'left'.");
		}

		if (!isset($condition['op']) || !\is_string($condition['op'])) {
			throw new ParseException("{$location}: {$path}.condition has no 'op'.");
		}

		if (!isset($step['then']) || !\is_array($step['then'])) {
			throw new ParseException("{$location}: {$path} has no 'then' key.");
		}

		if (isset($step['else']) && !\is_array($step['else'])) {
			throw new ParseException("{$location}: {$path}.else must be an array.");
		}

		$right = null;

		if (isset($condition['right'])) {
			if (!\is_string($condition['right'])) {
				throw new ParseException("{$location}: {$path}.condition.right must be a string.");
			}

			$right = Template::parse($condition['right']);
		}

		return new IfStep(
			condition: new Condition(
				left: Template::parse($condition['left']),
				op: $condition['op'],
				right: $right,
			),
			then: $this->parseSteps($step['then'], $location, "{$path}.then"),
			else: isset($step['else'])
				? $this->parseSteps($step['else'], $location, "{$path}.else")
				: [],
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseSet(array $step, string $location, string $path, ?string $name): SetStep
	{
		JsonSource::rejectUnknownKeys($step, ['type', 'key', 'value', 'name'], $location, $path);

		if (!isset($step['key']) || !\is_string($step['key'])) {
			throw new ParseException("{$location}: {$path} has no 'key' key.");
		}

		if (!isset($step['value']) || !\is_string($step['value'])) {
			throw new ParseException("{$location}: {$path} has no 'value' key.");
		}

		return new SetStep(
			key: $step['key'],
			value: Template::parse($step['value']),
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @throws ParseException
	 */
	private function parseForeach(array $step, string $location, string $path, ?string $name): ForeachStep
	{
		JsonSource::rejectUnknownKeys(
			$step,
			['type', 'over', 'as', 'steps', 'name'],
			$location,
			$path,
		);

		if (!isset($step['over']) || !\is_string($step['over'])) {
			throw new ParseException("{$location}: {$path} has no 'over' key.");
		}

		if (!isset($step['as']) || !\is_string($step['as'])) {
			throw new ParseException("{$location}: {$path} has no 'as' key.");
		}

		if (!isset($step['steps']) || !\is_array($step['steps'])) {
			throw new ParseException("{$location}: {$path} has no 'steps' key.");
		}

		return new ForeachStep(
			over: Template::parse($step['over']),
			as: $step['as'],
			steps: $this->parseSteps($step['steps'], $location, "{$path}.steps"),
			name: $name,
		);
	}


	/**
	 * @param  array<mixed> $step
	 * @return array<mixed>
	 * @throws ParseException
	 */
	private function objectOrEmpty(array $step, string $key, string $location, string $path): array
	{
		if (!isset($step[$key])) {
			return [];
		}

		if (!\is_array($step[$key])) {
			throw new ParseException("{$location}: {$path}.{$key} must be an object.");
		}

		return $step[$key];
	}
}
