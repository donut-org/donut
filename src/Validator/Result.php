<?php

declare(strict_types=1);

namespace Donut\Validator;


final class Result
{
	/** @var array<int, Problem> */
	private array $problems = [];


	public function add(Problem $problem): void
	{
		$this->problems[] = $problem;
	}


	/** @return array<int, Problem> */
	public function getProblems(): array
	{
		return $this->problems;
	}


	/** @return array<int, Problem> */
	public function getErrors(): array
	{
		return \array_values(\array_filter(
			$this->problems,
			fn(Problem $p) => $p->severity === Problem::Error
		));
	}


	/** @return array<int, Problem> */
	public function getWarnings(): array
	{
		return \array_values(\array_filter(
			$this->problems,
			fn(Problem $p) => $p->severity === Problem::Warning
		));
	}


	public function hasErrors(): bool
	{
		return $this->getErrors() !== [];
	}
}
