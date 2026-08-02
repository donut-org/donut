<?php

declare(strict_types=1);

namespace Donut\Validator;


/**
 * Které klíče v daném místě workflow existují.
 *
 * „Jistě" = zapsal je krok, který se určitě provedl. „Možná" = zapsal je krok
 * uvnitř větve if nebo těla foreach, které nemusí proběhnout.
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
	 * Kopie pro větev if nebo tělo foreach. Sdílí evidenci zapsaných
	 * a přečtených klíčů skrz merge, ale zápisy uvnitř neovlivní volajícího
	 * dřív, než se větev vyhodnotí.
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
	 * Převezme z větve zápisy jako „možná" a evidenci čtení a zápisů.
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


	/** @return array<int, string> */
	public function getWritten(): array
	{
		return \array_keys($this->written);
	}


	/** @return array<int, string> */
	public function getRead(): array
	{
		return \array_keys($this->read);
	}
}
