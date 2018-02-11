<?php

	namespace Donut;


	class Time
	{
		/** @var string  Y-m-d H:i:s in UTC */
		private $value;


		/**
		 * @param  string
		 */
		public function __construct($value)
		{
			$this->value = $value;
		}


		/**
		 * @return string
		 */
		public function getValue()
		{
			return $this->value;
		}


		/**
		 * @return bool
		 */
		public function isOlderThan(Time $time)
		{
			return $this->value < $time->getValue();
		}


		/**
		 * @param  Time
		 * @return int
		 */
		public function getMinutesTo(Time $time)
		{
			$start = $this->getDateTime();
			$end = $time->getDateTime();
			$diff = $start->diff($end);
			return ($diff->y * 365 * 24 * 60)
				+ ($diff->m * 30 * 24 * 60)
				+ ($diff->d * 24 * 60)
				+ ($diff->h * 60)
				+ ($diff->i);
		}


		/**
		 * @return \DateTime
		 */
		public function getDateTime()
		{
			return new \DateTime($this->value, new \DateTimeZone('UTC'));
		}


		/**
		 * @return Time
		 */
		public static function create($date)
		{
			if ($date instanceof \DateTime) {
				return new static($date->format('Y-m-d H:i:s'));
			}

			$date = new \DateTime($date, new \DateTimeZone('UTC'));
			$date->setTimeZone(new \DateTimeZone('UTC'));
			return new static($date->format('Y-m-d H:i:s'));
		}


		/**
		 * @param  Time
		 * @param  int
		 * @return Time
		 */
		public static function sub(Time $time, $minutes)
		{
			$datetime = $time->getDateTime();
			$datetime->sub(new \DateInterval('PT' . abs((int) $minutes) . 'M'));
			return new static($datetime->format('Y-m-d H:i:s'));
		}
	}
