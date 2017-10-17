<?php

	namespace Donut;

	use CzProject\Logger;
	use Ramsey\Uuid\Uuid;


	class Manager
	{
		/** @var IAdapter */
		private $adapter;

		/** @var ICurrentTimeFactory */
		private $currentTimeFactory;

		/** @var Logger\ILogger */
		private $logger;


		public function __construct(IAdapter $adapter, ICurrentTimeFactory $currentTimeFactory, Logger\ILogger $logger)
		{
			$this->adapter = $adapter;
			$this->currentTimeFactory = $currentTimeFactory;
			$this->logger = $logger;
		}


		public function log($subject, $text = NULL, Message $message = NULL)
		{
			$this->adapter->log($subject, $text, $message, $this->currentTimeFactory->createTime());
		}


		/**
		 * @return void
		 */
		public function createMessage($queue, array $data, Time $date = NULL)
		{
			$id = Uuid::uuid4();
			$this->logger->log('Created new message ' . $id->toString() . " in queue '$queue'");
			$created = $this->currentTimeFactory->createTime();
			$message = new Message($id, $queue, $data, $date ? $date : $created, $created, Message::STATUS_NEW);
			$this->adapter->createMessage($message);
		}


		/**
		 * @param  string
		 * @param  string
		 * @return bool
		 */
		public function existsItem($group, $itemId)
		{
			return $this->adapter->existsItem($group, $itemId);
		}


		/**
		 * @param  string
		 * @param  string
		 * @return void
		 */
		public function saveItem($group, $itemId)
		{
			$this->adapter->saveItem($group, $itemId);
		}
	}
