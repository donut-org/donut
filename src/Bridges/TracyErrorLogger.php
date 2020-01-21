<?php

	namespace Donut\Bridges;


	class TracyErrorLogger implements \Donut\IErrorLogger
	{
		/**
		 * {@inheritdoc}
		 */
		public function log($e)
		{
			\Tracy\Debugger::log($e, \Tracy\Debugger::EXCEPTION);
		}
	}
