<?php

	namespace Donut;


	interface IErrorLogger
	{
		/**
		 * @param  \Exception|\Throwable
		 * @return void
		 */
		function log($e);
	}
