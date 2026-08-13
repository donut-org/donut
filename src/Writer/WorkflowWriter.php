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


/**
 * Workflow na pole. Inverze WorkflowParseru.
 *
 * Pořadí klíčů odpovídá tomu, jak jsou soubory psané dnes. Volitelná pole
 * se vynechávají, když nejsou vyplněná; `required` u vstupů se vypisuje
 * vždycky (viz InputWriter).
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

		// steps se vypisují i prázdné — parser je vyžaduje.
		$data['steps'] = $this->stepsToArray($workflow->steps);

		return $data;
	}


	/**
	 * @param  array<int, Step> $steps
	 * @return array<int, array<string, mixed>>
	 */
	private function stepsToArray(array $steps): array
	{
		return \array_map(fn(Step $step): array => $this->stepToArray($step), $steps);
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

			// U kroku je null „nenastaveno" a false vědomé vypnutí —
			// na rozdíl od kamene, kde je false výchozí hodnota.
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
			// then se vypisuje i prázdné — parser ho vyžaduje.
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

		// Nová implementace Step se nesmí tiše přeskočit — spadlo by to až
		// tím, že by z uloženého souboru zmizel celý krok.
		throw new \LogicException('neznámý typ kroku ' . $step::class);
	}
}
