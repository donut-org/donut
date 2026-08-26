<?php

declare(strict_types=1);

namespace Donut\Validator;


/**
 * Which keys exist at a given point in the workflow.
 *
 * "Known" = written by a step that definitely ran. "Maybe" = written by a
 * step inside an if branch or a foreach body, which may not run.
 */
final class KeyFlow
{
	/** @var array<string, true> */
	private array $known = [];

	/** @var array<string, true> */
	private array $maybe = [];

	/** @var array<string, true> */
	private array $written = [];

	/** @var array<string, true> */
	private array $read = [];


	/** @param array<int, string> $known */
	public function __construct(array $known = [])
	{
		foreach ($known as $key) {
			$this->known[$key] = true;
		}
	}


	public function isKnown(string $key): bool
	{
		return isset($this->known[$key]);
	}


	public function isMaybe(string $key): bool
	{
		return isset($this->maybe[$key]);
	}


	public function write(string $key): void
	{
		$this->known[$key] = true;
		$this->written[$key] = true;
	}


	public function writeMaybe(string $key): void
	{
		$this->maybe[$key] = true;
		$this->written[$key] = true;
	}


	public function markRead(string $key): void
	{
		$this->read[$key] = true;
	}


	/**
	 * A copy for an if branch or a foreach body. Shares the record of
	 * written and read keys through merge, but writes inside do not affect
	 * the caller until the branch is merged in.
	 */
	public function branch(): self
	{
		$branch = new self;
		$branch->known = $this->known;
		$branch->maybe = $this->maybe;
		$branch->written = $this->written;
		$branch->read = $this->read;

		return $branch;
	}


	/**
	 * Takes over the branch's writes as "maybe", plus its record of reads
	 * and writes.
	 */
	public function mergeAsMaybe(self $branch): void
	{
		foreach (\array_keys($branch->known) as $key) {
			if (!isset($this->known[$key])) {
				$this->maybe[$key] = true;
			}
		}

		foreach (\array_keys($branch->maybe) as $key) {
			$this->maybe[$key] = true;
		}

		$this->written += $branch->written;
		$this->read += $branch->read;
	}


	/**
	 * Merges both branches of an if. A key known in both branches is known
	 * here too — unlike mergeAsMaybe(), which would degrade it to "maybe"
	 * even though there is no escape from the if. A key known in only one
	 * branch stays "maybe".
	 */
	public function mergeBranches(self $a, self $b): void
	{
		foreach ([...\array_keys($a->known), ...\array_keys($b->known)] as $key) {
			if (isset($this->known[$key])) {
				continue;
			}

			if (isset($a->known[$key]) && isset($b->known[$key])) {
				$this->known[$key] = true;

			} else {
				$this->maybe[$key] = true;
			}
		}

		foreach ([...\array_keys($a->maybe), ...\array_keys($b->maybe)] as $key) {
			if (!isset($this->known[$key])) {
				$this->maybe[$key] = true;
			}
		}

		$this->written += $a->written + $b->written;
		$this->read += $a->read + $b->read;
	}


	/**
	 * array_keys() converts an array key made up of only digits to int — the
	 * key name "456" is valid (Template::isKeyName()), strval() converts it
	 * back to string, as the return type declares.
	 *
	 * @return array<int, string>
	 */
	public function getWritten(): array
	{
		return \array_map(\strval(...), \array_keys($this->written));
	}


	/** @return array<int, string> */
	public function getRead(): array
	{
		return \array_map(\strval(...), \array_keys($this->read));
	}
}
