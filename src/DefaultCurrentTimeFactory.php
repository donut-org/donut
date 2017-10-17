<?php

	namespace Donut;


	class DefaultCurrentTimeFactory implements ICurrentTimeFactory
	{
		/**
		 * {@inheritdoc}
		 */
		public function createTime()
		{
			$date = new \DateTime;
			$date->setTimezone(new \DateTimeZone('UTC'));
			return new Time($date->format('Y-m-d H:i:s'));
		}
	}
