<?php

declare(strict_types=1);

namespace Donut\Format;


/**
 * Společný typ pro položky pole steps.
 */
interface Step
{
	public function getName(): ?string;
}
