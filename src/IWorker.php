<?php

	namespace Donut;


	interface IWorker
	{
		/**
		 * @return void
		 */
		function processMessage(Message $message, Manager $manager);
	}
