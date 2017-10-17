<?php

	namespace Donut\Adapters;

	use Donut\IAdapter;
	use Donut\IProducer;
	use Donut\Message;
	use Donut\Time;
	use Nette\Utils\Json;
	use Ramsey\Uuid\Uuid;


	class DibiSqliteAdapter implements IAdapter
	{
		/** @var \Dibi\Connection */
		private $connection;


		/**
		 * @param  string
		 */
		public function __construct($file)
		{
			$this->connection = new \Dibi\Connection(array(
				'driver' => 'sqlite3',
				'file' => $file,
			));
		}


		/**
		 * {@inheritdoc}
		 */
		public function createMessage(Message $message)
		{
			$this->connection->query('INSERT INTO [message]', array(
				'id%bin' => $message->getId()->getBytes(),
				'queue%s' => $message->getQueue(),
				'data%s' => Json::encode($message->getData()),
				'date%s' => $message->getDate()->getValue(),
				'created%s' => $message->getCreated()->getValue(),
				'status%i' => $message->getStatus(),
				'processed' => NULL,
			));
		}


		/**
		 * {@inheritdoc}
		 */
		public function fetchMessage()
		{
			$row = $this->connection->fetch('SELECT * FROM [message] WHERE [status] = %i AND [processed] IS NULL ORDER BY [date] LIMIT 1', Message::STATUS_NEW);

			if ($row === FALSE) {
				return NULL;
			}

			return new Message(
				Uuid::fromBytes($row->id),
				$row->queue,
				Json::decode($row->data, Json::FORCE_ARRAY),
				Time::create($row->date),
				Time::create($row->created),
				$row->status,
				$row->processed ? Time::create($row->processed) : NULL
			);
		}


		/**
		 * {@inheritdoc}
		 */
		public function markAsDone(Message $message, Time $processed)
		{
			$this->connection->query('DELETE FROM [message] WHERE [id] = %bin', $message->getId()->getBytes());
		}


		/**
		 * {@inheritdoc}
		 */
		public function markAsFailed(Message $message, Time $processed)
		{
			$this->connection->query(
				'UPDATE [message] SET [status] = %i, [processed] = %s WHERE [id] = %bin',
				Message::STATUS_FAILED,
				$processed->getValue(),
				$message->getId()->getBytes()
			);
		}


		/**
		 * {@inheritdoc}
		 */
		public function log($subject, $text = NULL, Message $message = NULL, Time $date)
		{
			$this->connection->query('INSERT INTO [log]', array(
				'id%bin' => Uuid::uuid4()->getBytes(),
				'subject%s' => $subject,
				'text%s' => $text,
				'message_id%bin' => $message ? $message->getId()->getBytes() : NULL,
				'date%s' => $date->getValue(),
			));
		}


		/**
		 * {@inheritdoc}
		 */
		public function getProducerLastRun(IProducer $producer)
		{
			$row = $this->connection->fetch('SELECT * FROM [producer] WHERE [producer] = %s', $producer->getUniqueId());

			if ($row && $row->lastrun !== NULL) {
				return Time::create($row->lastrun);
			}

			return NULL;
		}


		/**
		 * {@inheritdoc}
		 */
		public function saveProducerLastRun(IProducer $producer, Time $lastrun)
		{
			try {
				$this->connection->query('INSERT INTO [producer]', array(
					'producer%s' => $producer->getUniqueId(),
					'lastrun%s' => $lastrun->getValue(),
				));

			} catch (\DibiDriverException $e) {
				if (substr($e->getMessage(), 0, 24) !== 'UNIQUE constraint failed') {
					throw $e;
				}

				$this->connection->query('UPDATE [producer] SET [lastrun] = %s WHERE [producer] = %s', $lastrun->getValue(), $producer->getUniqueId());
			}
		}


		/**
		 * {@inheritdoc}
		 */
		public function existsItem($group, $itemId)
		{
			$row = $this->connection->fetch('SELECT * FROM [item] WHERE [group_name] = %s AND [item] = %s', $group, $itemId);
			return $row !== FALSE && $row !== NULL;
		}


		/**
		 * {@inheritdoc}
		 */
		public function saveItem($group, $itemId)
		{
			$this->connection->query('INSERT INTO [item]', array(
				'group_name%s' => $group,
				'item%s' => $itemId,
				'date%s' => date('Y-m-d H:i:s')
			));
		}
	}
