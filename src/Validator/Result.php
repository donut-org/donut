<?php

declare(strict_types=1);

namespace Donut\Validator;


final class Result
{
	/** @var array<int, Problem> */
	private array $problems = [];

	/** @var list<string> */
	private array $readKeys = [];

	/** @var list<string> */
	private array $writtenKeys = [];

	private bool $keysSet = false;


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


	/**
	 * Called by Validator at the end of validate(). It belongs nowhere else
	 * — Result is otherwise just a problem collector, and these sets exist
	 * for the GUI, which derives the same map with its own traversal and
	 * compares the two in a test.
	 *
	 * Today this holds only by coincidence — `new Result` is the only one
	 * in the repository and validate() has no early return. A second call
	 * (foreign or its own) would silently overwrite both sets, hence the
	 * explicit guard.
	 *
	 * @param array<int, string> $read
	 * @param array<int, string> $written
	 */
	public function setKeys(array $read, array $written): void
	{
		if ($this->keysSet) {
			throw new \LogicException('setKeys() has already been called.');
		}

		$this->keysSet = true;

		\sort($read);
		\sort($written);

		$this->readKeys = $read;
		$this->writtenKeys = $written;
	}


	/** @return list<string> alphabetically */
	public function getReadKeys(): array
	{
		return $this->readKeys;
	}


	/** @return list<string> alphabetically */
	public function getWrittenKeys(): array
	{
		return $this->writtenKeys;
	}


	public function hasErrors(): bool
	{
		return $this->getErrors() !== [];
	}
}
