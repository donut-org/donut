<?php

	namespace Donut\Adapters;

	use Donut\IAdapter;
	use Donut\IProducer;
	use Donut\Message;
	use Donut\Time;
	use Nette\Utils\Json;
	use Ramsey\Uuid\Uuid;


	class MemoryAdapter implements IAdapter
	{
		/** @var string */
		private $logFile;

		/** @var Message[] */
		private $messages = array();

		/** @var array  [producerId => Time] */
		private $producers = array();

		/** @var array  [groupId => [key => TRUE]] */
		private $items = array();


		public function __construct($logFile)
		{
			$this->logFile = fopen($logFile, 'w');
		}


		/**
		 * {@inheritdoc}
		 */
		public function createMessage(Message $message)
		{
			$this->log('Created message', NULL, $message);
			$this->messages[] = $message;
		}


		/**
		 * {@inheritdoc}
		 */
		public function fetchMessage()
		{
			return array_shift($this->messages);
		}


		/**
		 * {@inheritdoc}
		 */
		public function markAsDone(Message $message, Time $processed)
		{
			$this->log('Mark as done', NULL, $message);
		}


		/**
		 * {@inheritdoc}
		 */
		public function markAsFailed(Message $message, Time $processed)
		{
			$this->log('Mark as failed', NULL, $message);
		}


		/**
		 * {@inheritdoc}
		 */
		public function log($subject, $text = NULL, Message $message = NULL, Time $date)
		{
			fwrite($this->logFile, implode("\n", array(
				'== ' . $date->getValue() . ' =========================================================',
				$subject,
				'-- Text: ---------------------------------------',
				$text,
				'-- Message: ------------------------------------',
				$message ? $message->getId()->toString() : NULL,
				$message ? json_encode($message->getData(), JSON_PRETTY_PRINT) : NULL,
				'',
			)));
		}


		/**
		 * {@inheritdoc}
		 */
		public function getProducerLastRun(IProducer $producer)
		{
			$id = $this->hashId($producer->getUniqueId());
			return isset($this->producers[$id]) ? $this->producers[$id] : NULL;
		}


		/**
		 * {@inheritdoc}
		 */
		public function saveProducerLastRun(IProducer $producer, Time $lastrun)
		{
			$id = $this->hashId($producer->getUniqueId());
			$this->producers[$id] = $lastrun;
		}


		/**
		 * {@inheritdoc}
		 */
		public function existsItem($group, $itemId)
		{
			return isset($this->items[$this->hashId($group)][$this->hashId($itemId)]);
		}


		/**
		 * {@inheritdoc}
		 */
		public function saveItem($group, $itemId)
		{
			$this->items[$this->hashId($group)][$this->hashId($itemId)] = TRUE;
		}


		/**
		 * {@inheritdoc}
		 */
		private function hashId($id)
		{
			return md5($id, TRUE);
		}
	}
