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
	 * Volá Validator na konci validate(). Jinam nepatří — Result je jinak
	 * jen sběrač problémů a tyhle množiny existují kvůli GUI, které si
	 * tutéž mapu odvozuje vlastním průchodem a testem se s tímhle porovnává.
	 *
	 * Dnes to drží jen shodou okolností — `new Result` je v repozitáři
	 * jediné a validate() nemá časný return. Druhé volání (cizí i vlastní)
	 * by tiše přepsalo obě množiny, proto se to hlídá explicitně.
	 *
	 * @param array<int, string> $read
	 * @param array<int, string> $written
	 */
	public function setKeys(array $read, array $written): void
	{
		if ($this->keysSet) {
			throw new \LogicException('setKeys() už bylo jednou zavoláno.');
		}

		$this->keysSet = true;

		\sort($read);
		\sort($written);

		$this->readKeys = $read;
		$this->writtenKeys = $written;
	}


	/** @return list<string> abecedně */
	public function getReadKeys(): array
	{
		return $this->readKeys;
	}


	/** @return list<string> abecedně */
	public function getWrittenKeys(): array
	{
		return $this->writtenKeys;
	}


	public function hasErrors(): bool
	{
		return $this->getErrors() !== [];
	}
}
