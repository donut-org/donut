<?php

declare(strict_types=1);

namespace Donut\Runner;


/**
 * The run never started at all — the workflow failed validation, or is
 * missing a required input. Not a single step ran.
 *
 * A subclass, not a separate type, so a caller that doesn't care about the
 * difference can keep catching just RunFailedException.
 */
final class CannotStartException extends RunFailedException
{
}
