<?php

declare(strict_types=1);

namespace Donut\Writer;

use Donut\Format\ForeachStep;
use Donut\Format\IfStep;
use Donut\Format\RunStep;
use Donut\Format\SetStep;
use Donut\Format\Step;
use Donut\Format\Workflow;
use Donut\Template;
use Nette\IOException;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * Workflow to an array. Inverse of WorkflowParser.
 *
 * The key order matches how the files are written today. Optional fields
 * are omitted when unfilled; `required` on inputs is always written out
 * (see InputWriter).
 */
final class WorkflowWriter
{
	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Workflow $workflow): array
	{
		$data = ['name' => $workflow->name];

		if ($workflow->description !== null) {
			$data['description'] = $workflow->description;
		}

		if ($workflow->inputs !== []) {
			$data['inputs'] = InputWriter::toArray($workflow->inputs);
		}

		// steps are written even when empty — the parser requires them.
		$data['steps'] = $this->stepsToArray($workflow->steps);

		return $data;
	}


	/**
	 * The path comes from outside, not derived from the name — see BlockWriter.
	 *
	 * @throws WriteException when the workflow's name does not match the file
	 *                        name, the data cannot be encoded to JSON, or the
	 *                        file cannot be written
	 */
	public function writeFile(Workflow $workflow, string $path): void
	{
		$expected = \basename($path, '.json');

		if ($workflow->name !== $expected) {
			throw new WriteException(
				"{$path}: name '{$workflow->name}' does not match the file name '{$expected}'."
			);
		}

		try {
			$content = Json::encode($this->toArray($workflow), Json::PRETTY) . "\n";

		} catch (JsonException $e) {
			throw new WriteException("{$path}: data could not be encoded to JSON: {$e->getMessage()}", 0, $e);
		}

		try {
			FileSystem::writeAtomic($path, $content);

		} catch (IOException $e) {
			throw new WriteException("{$path}: file could not be written: {$e->getMessage()}", 0, $e);
		}
	}


	/**
	 * array_map() preserves keys; steps is array<int, Step>, not a list, so a
	 * gap (e.g. after unset() in the GUI) would encode as a JSON object
	 * instead of an array without array_values(). One place covers the
	 * top-level steps, then, else, and foreach.steps — they all go through
	 * this method.
	 *
	 * @param  array<int, Step> $steps
	 * @return array<int, array<string, mixed>>
	 */
	private function stepsToArray(array $steps): array
	{
		return \array_values(\array_map(fn(Step $step): array => $this->stepToArray($step), $steps));
	}


	/**
	 * @return array<string, mixed>
	 */
	private function stepToArray(Step $step): array
	{
		if ($step instanceof RunStep) {
			$data = ['type' => 'run'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['block'] = $step->block;

			if ($step->in !== []) {
				$data['in'] = \array_map(
					fn(Template $template): string => $template->getSource(),
					$step->in,
				);
			}

			if ($step->out !== []) {
				$data['out'] = $step->out;
			}

			if ($step->timeout !== null) {
				$data['timeout'] = $step->timeout;
			}

			// For a step, null is "unset" and false a deliberate opt-out —
			// unlike a block, where false is the default value.
			if ($step->allowFailure !== null) {
				$data['allow_failure'] = $step->allowFailure;
			}

			return $data;
		}

		if ($step instanceof SetStep) {
			$data = ['type' => 'set'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['key'] = $step->key;
			$data['value'] = $step->value->getSource();

			return $data;
		}

		if ($step instanceof IfStep) {
			$data = ['type' => 'if'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$condition = [
				'left' => $step->condition->left->getSource(),
				'op' => $step->condition->op,
			];

			if ($step->condition->right !== null) {
				$condition['right'] = $step->condition->right->getSource();
			}

			$data['condition'] = $condition;
			// then is written even when empty — the parser requires it.
			$data['then'] = $this->stepsToArray($step->then);

			if ($step->else !== []) {
				$data['else'] = $this->stepsToArray($step->else);
			}

			return $data;
		}

		if ($step instanceof ForeachStep) {
			$data = ['type' => 'foreach'];

			if ($step->name !== null) {
				$data['name'] = $step->name;
			}

			$data['over'] = $step->over->getSource();
			$data['as'] = $step->as;
			$data['steps'] = $this->stepsToArray($step->steps);

			return $data;
		}

		// A new Step implementation must not be silently skipped — it would
		// only surface as a whole step disappearing from the saved file.
		throw new \LogicException('unknown step type ' . $step::class);
	}
}
