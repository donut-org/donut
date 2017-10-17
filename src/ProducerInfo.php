<?php

	namespace Donut;


	class ProducerInfo
	{
		/** @var IProducer */
		public $producer;

		/** @var Period|NULL */
		public $period;


		public function __construct(IProducer $producer, Period $period = NULL)
		{
			$this->producer = $producer;
			$this->period = $period;
		}
	}
