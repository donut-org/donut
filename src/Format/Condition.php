<?php

declare(strict_types=1);

namespace Donut\Format;

use Donut\Template;


final class Condition
{
	/** Operators per specification section 2. */
	public const Operators = [
		'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'empty', 'not_empty',
	];

	/** Operators that ignore the 'right' key. */
	public const UnaryOperators = ['empty', 'not_empty'];

	public function __construct(
		public readonly Template $left,
		public readonly string $op,
		public readonly ?Template $right = null,
	) {
	}
}
