<?php

	namespace Donut;


	interface IAdapter
	{
		/**
		 * @return void
		 */
		function createMessage(Message $message);


		/**
		 * @return Message|NULL
		 */
		function fetchMessage();


		/**
		 * @return void
		 */
		function markAsDone(Message $message, Time $processed);


		/**
		 * @return void
		 */
		function markAsFailed(Message $message, Time $processed);


		/**
		 * @param  string
		 * @param  string|NULL
		 * @param  Message|NULL
		 * @return void
		 */
		function log($subject, $text = NULL, Message $message = NULL, Time $date);


		/**
		 * @return Time|NULL
		 */
		function getProducerLastRun(IProducer $producer);


		/**
		 * @return void
		 */
		function saveProducerLastRun(IProducer $producer, Time $lastrun);


		/**
		 * @param  string
		 * @param  string
		 * @return bool
		 */
		function existsItem($group, $itemId);


		/**
		 * @param  string
		 * @param  string
		 * @return void
		 */
		function saveItem($group, $itemId);
	}
