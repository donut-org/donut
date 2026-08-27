<?php

declare(strict_types=1);

namespace Donut\Parser;


/**
 * The named block or workflow isn't there at all.
 *
 * Distinct from its parent, which also covers a file that exists and won't
 * parse. The two are different answers to a URL: nothing to show here, as
 * against here is something broken. Only the first is a 404 — the GUI
 * turns it into one, and a broken file must not be hidden behind that.
 *
 * It extends ParseException so that every `catch (ParseException)` written
 * before this distinction existed still catches it.
 */
final class NotFoundException extends ParseException
{
}
