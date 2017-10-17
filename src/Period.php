<?php

	namespace Donut;


	class Period
	{
		/** @var int|NULL  in minutes */
		private $interval;


		/**
		 * @param  int|NULL
		 */
		public function __construct($interval = NULL)
		{
			$this->interval = $interval;
		}


		/**
		 * @return  int|NULL
		 */
		public function getInterval()
		{
			return $this->interval;
		}


		/**
		 * @param  string
		 * @return static
		 */
		public static function every($interval)
		{
			$interval = explode(' ', $interval);
			$minutes = 0;

			foreach ($interval as $part) {
				$num = substr($part, 0, -1);
				$unit = substr($part, -1);

				if ($unit === 'h') {
					$num *= 60;

				} elseif ($unit === 'm') { // nothing
					// nothing

				} else {
					$num = $part;
				}

				$minutes += $num;
			}

			$period = new static($minutes);
			return $period;
		}
	}
