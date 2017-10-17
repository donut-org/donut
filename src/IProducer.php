<?php

	namespace Donut;


	interface IProducer
	{
		/**
		 * @return string
		 */
		function getUniqueId();


		/**
		 * @return void
		 */
		function run(Manager $manager, Time $lastrun = NULL);
	}
