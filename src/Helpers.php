<?php

	namespace Donut;


	class Helpers
	{
		/**
		 * @param  string
		 * @return string
		 * @throws \Donut\InvalidStateException
		 */
		public static function fetchUrl($url)
		{
			$s = @file_get_contents($url);

			if (is_string($s)) {
				return $s;
			}

			throw new \Donut\InvalidStateException("Download of content from URL '$url' failed.");
		}


		/**
		 * @param  string|callback
		 * @param  array
		 * @param  object|NULL
		 * @return string
		 */
		public static function formatText($mask, array $placeholders, $argument = NULL)
		{
			if (is_string($mask)) {
				return strtr($mask, $placeholders);
			}

			return call_user_func($mask, $argument);
		}


		/**
		 * @param  string
		 * @return string
		 */
		public static function stripWhitespace($s)
		{
			return trim(preg_replace('#[ \t\r\n]+#', ' ', (string) $s)); // strip whitespaces
		}
	}
