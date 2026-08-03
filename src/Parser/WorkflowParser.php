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
 * JSON souboru z workflows/ na objekt Workflow.
 *
 * Kontroluje jen strukturu jednoho souboru — že kroky mají povinné klíče
 * správných typů. Existenci kamenů a tok klíčů řeší validátor.
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
				"{$path}: name '{$workflow->name}' neodpovídá názvu souboru '{$expected}'."
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
			throw new ParseException("{$location}: klíč 'name' je povinný a musí být neprázdný řetězec.");
		}

		if (!isset($data['steps']) || !\is_array($data['steps'])) {
			throw new ParseException("{$location}: klíč 'steps' je povinný a musí být pole.");
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
				throw new ParseException("{$location}: {$path}[{$i}] musí být objekt.");
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
			throw new ParseException("{$location}: {$path} nemá klíč 'type'.");
		}

		$name = JsonSource::optionalString($step, 'name', $location, "{$path}.name");

		return match ($type) {
			'run' => $this->parseRun($step, $location, $path, $name),
			'if' => $this->parseIf($step, $location, $path, $name),
			'set' => $this->parseSet($step, $location, $path, $name),
			'foreach' => $this->parseForeach($step, $location, $path, $name),
			default => throw new ParseException("{$location}: {$path} má neznámý typ kroku '{$type}'."),
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
			throw new ParseException("{$location}: {$path} nemá klíč 'block'.");
		}

		$in = [];

		foreach ($this->objectOrEmpty($step, 'in', $location, $path) as $key => $value) {
			if (!\is_string($key) || !\is_string($value)) {
				throw new ParseException("{$location}: {$path}.in musí být objekt řetězec => řetězec.");
			}

			$in[$key] = Template::parse($value);
		}

		$out = [];

		foreach ($this->objectOrEmpty($step, 'out', $location, $path) as $channel => $key) {
			if (!\is_string($channel) || !\is_string($key)) {
				throw new ParseException("{$location}: {$path}.out musí být objekt řetězec => řetězec.");
			}

			$out[$channel] = $key;
		}

		$allowFailure = isset($step['allow_failure'])
			? JsonSource::parseAllowFailure($step['allow_failure'], $location, "{$path}.allow_failure")
			: null;

		$timeout = null;

		if (isset($step['timeout'])) {
			if (!\is_int($step['timeout']) || $step['timeout'] < 0) {
				throw new ParseException("{$location}: {$path}.timeout musí být nezáporné celé číslo.");
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
			throw new ParseException("{$location}: {$path} nemá klíč 'condition'.");
		}

		$condition = $step['condition'];

		JsonSource::rejectUnknownKeys($condition, ['left', 'op', 'right'], $location, "{$path}.condition");

		if (!isset($condition['left']) || !\is_string($condition['left'])) {
			throw new ParseException("{$location}: {$path}.condition nemá 'left'.");
		}

		if (!isset($condition['op']) || !\is_string($condition['op'])) {
			throw new ParseException("{$location}: {$path}.condition nemá 'op'.");
		}

		if (!isset($step['then']) || !\is_array($step['then'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'then'.");
		}

		if (isset($step['else']) && !\is_array($step['else'])) {
			throw new ParseException("{$location}: {$path}.else musí být pole.");
		}

		$right = null;

		if (isset($condition['right'])) {
			if (!\is_string($condition['right'])) {
				throw new ParseException("{$location}: {$path}.condition.right musí být řetězec.");
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
			throw new ParseException("{$location}: {$path} nemá klíč 'key'.");
		}

		if (!isset($step['value']) || !\is_string($step['value'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'value'.");
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
			throw new ParseException("{$location}: {$path} nemá klíč 'over'.");
		}

		if (!isset($step['as']) || !\is_string($step['as'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'as'.");
		}

		if (!isset($step['steps']) || !\is_array($step['steps'])) {
			throw new ParseException("{$location}: {$path} nemá klíč 'steps'.");
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
			throw new ParseException("{$location}: {$path}.{$key} musí být objekt.");
		}

		return $step[$key];
	}
}
