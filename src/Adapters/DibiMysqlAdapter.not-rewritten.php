<?php

	namespace Donut\Adapters;

	use Donut\IAdapter;
	use Donut\Message;
	use Donut\Time;
	use Nette\Utils\Json;
	use Ramsey\Uuid\Uuid;


	class DibiMysqlAdapter implements IAdapter
	{
		/** @var \Dibi\Connection */
		private $connection;


		public function __construct(\Dibi\Connection $connection)
		{
			$this->connection = $connection;
		}


		/**
		 * @return void
		 */
		public function createMessage(Message $message)
		{
			$this->connection->query('INSERT INTO [message]', array(
				'id' => $message->getId(),
				'queue' => $message->getQueue(),
				'data' => Json::encode($message->getData()),
				'created' => $message->getCreated(),
				'status' => $message->getStatus(),
				'processed' => $message->getProcessed(),
			));
		}


		/**
		 * @return Message|NULL
		 */
		public function fetchMessage()
		{
			$row = $this->connection->fetch('SELECT * FROM [message] ORDER BY [created] LIMIT 1');

			if ($row === FALSE) {
				return NULL;
			}

			return new Message(
				$row->id,
				$row->queue,
				Json::decode($row->data, Json::FORCE_ARRAY),
				Time::create($row->created),
				$row->status,
				$row->processed ? Time::create($row->processed) : NULL
			);
		}


		/**
		 * @return void
		 */
		public function markAsDone(Message $message)
		{
			$this->connection->query(
				'UPDATE [message] SET [status] = %i, [processed] = %t WHERE [id] = %i',
				Message::STATUS_DONE,
				Time::getCurrentTime(),
				$message->getId()
			);
		}


		/**
		 * @return void
		 */
		public function markAsFailed(Message $message)
		{
			$this->connection->query(
				'UPDATE [message] SET [status] = %i, [processed] = %t WHERE [id] = %i',
				Message::STATUS_FAILED,
				Time::getCurrentTime(),
				$message->getId()
			);
		}


		/**
		 * @param  string
		 * @param  string|NULL
		 * @param  Message|NULL
		 * @return void
		 */
		public function log($subject, $text = NULL, Message $message = NULL)
		{
			$this->connection->query('INSERT INTO [log]', array(
				'id' => Uuid::uuid1(),
				'subject' => $subject,
				'text' => $text,
				'message' => $message->getId(),
				'date' => Time::getCurrentTime(),
			));
		}


		/**
		 * @return Time|NULL
		 */
		public function getProducerLastRun(IProducer $producer)
		{
			$row = $this->connection->fetch('SELECT * FROM [producer] WHERE [producer] = %s', $this->getProducerId($producer));

			if ($row === FALSE) {
				return NULL;
			}

			return Time::create($row->lastrun);
		}


		/**
		 * @return void
		 */
		public function saveProducerLastRun(IProducer $producer, Time $lastrun)
		{
			$producerId = $this->getProducerId($producer);

			try {
				$this->connection->query('INSERT INTO [producer]', array(
					'producer' => $producerId,
					'lastrun' => $lastrun,
				));

			} catch (\DibiDriverException $e) {
				if (substr($e->getMessage(), 0, 15) !== 'Duplicate entry') {
					throw $e;
				}

				$this->connection->query('UPDATE [producer] SET lastrun = %t WHERE [producer] = %s', $lastrun, $producerId);
			}
		}


		/**
		 * @param  IProducer
		 * @param  string
		 * @return bool
		 */
		public function hasProducerItem(IProducer $producer, $itemId)
		{
			return $this->connection->fetch('SELECT * FROM [producer_item] WHERE [producer] = %s AND [item-id] = %s', $this->getProducerId($producer), $itemId) !== FALSE;
		}


		/**
		 * @param  IProducer
		 * @param  string
		 * @param  Time
		 * @return void
		 */
		public function saveProducerItem(IProducer $producer, $itemId, Time $date)
		{
			$this->connection->query('INSERT INTO [producer_item]', array(
				'producer' => $this->getProducerId($producer),
				'item_id' => $itemId,
				'date' => $date->getDateTime(),
			));
		}


		/**
		 * @return string
		 */
		private function getProducerId()
		{
			return md5($producer->getName());
		}
	}
