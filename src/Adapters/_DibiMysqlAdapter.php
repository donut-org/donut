<?php

	namespace Copro\Adapters;

	use DibiConnection;
	use Copro\Event;
	use Copro\IAdapter;
	use Copro\IEntity;
	use Copro\IChannel;
	use Copro\ILogger;
	use Copro\IProducer;
	use Copro\ChannelInfo;
	use Copro\Utils\Time;
	use Nette\Utils\Json;
	use Nette\Utils\Strings;


	class DibiMysqlAdapter implements IAdapter, ILogger
	{
		/** @var DibiConnection */
		private $connection;

		/** @var array  [name => id] */
		private $channels;


		public function __construct($connection)
		{
			if (!($connection instanceof DibiConnection)) {
				$connection = new DibiConnection($connection);
			}
			$this->connection = $connection;
		}


		public function log($subject, $message = NULL, Event $event = NULL)
		{
			$this->connection->query('INSERT INTO [log]', array(
				'time' => $this->getTime(),
				'subject' => $subject,
				'message' => $message,
				'event_id' => isset($event) ? $event->getId() : NULL,
				'token' => Strings::random(32),
			));
		}


		/**
		 * @return void
		 */
		public function begin()
		{
		}


		/**
		 * @return void
		 */
		public function finish()
		{
			// $this->connection->commit();
		}


		/**
		 * @param  IChannel
		 * @param  IEntity
		 * @param  bool
		 * @return void
		 * @throws \RuntimeException
		 */
		public function createEvent(IChannel $channel, IEntity $entity, $persistent)
		{
			$this->connection->query('INSERT INTO [event]', array(
				'channel_id' => $this->getChannelId($channel),
				'type' => (string) $entity->getType(),
				'data' => Json::encode($entity->getData()),
				'createdAt' => $this->getTime(),
				'processed' => NULL,
				'persistent' => (int) (bool) $persistent,
			));
		}


		/**
		 * @return Event|FALSE
		 */
		public function getEvent()
		{
			$row = $this->connection->fetch('
				SELECT [event].*, [channel].[name] AS [channel_name] FROM [event]
				JOIN [channel] ON [event].[channel_id] = [channel].[id]
				LIMIT 1
			');

			if ($row === FALSE) {
				return FALSE;
			}

			return new Event(
				$row->id,
				$row->channel_name,
				$row->type,
				Json::decode($row->data, Json::FORCE_ARRAY),
				$row->createdAt,
				(bool) $row->persistent
			);
		}


		/**
		 * @param  Event
		 * @return void
		 */
		public function failEvent(Event $event)
		{
			try {
				$this->connection->begin();
				$this->copyEvent('event_fail', $event);
				$this->markEventAsProcessed('event_fail', $event);
				$this->deleteEvent($event);
				$this->connection->commit();

			} catch (\Exception $e) {
				$this->connection->rollback();
				throw $e;
			}
		}


		/**
		 * @param  Event
		 * @return void
		 */
		public function doneEvent(Event $event)
		{
			try {
				$this->connection->begin();

				if ($event->isPersistent()) {
					$this->copyEvent('event_done', $event);
					$this->markEventAsProcessed('event_done', $event);
				}

				$this->deleteEvent($event);
				$this->connection->commit();

			} catch (\Exception $e) {
				$this->connection->rollback();
				throw $e;
			}
		}


		private function copyEvent($tableName, Event $event)
		{
			$this->connection->query('INSERT INTO %n SELECT * FROM [event] WHERE [id] = %i', $tableName, $event->getId());
		}


		private function markEventAsProcessed($tableName, Event $event)
		{
			$this->connection->query('UPDATE %n SET [processed] = %t WHERE [id] = %i',
				$tableName,
				$this->getTime(),
				$event->getId()
			);
		}


		private function deleteEvent(Event $event)
		{
			$this->connection->query('DELETE FROM [event] WHERE [id] = %i', $event->getId());
		}


		/**
		 * @return \DateTime
		 */
		private function getTime()
		{
			return Time::getCurrentTime();
		}


		/**
		 * @return \DateTime|NULL
		 */
		public function getProducerLastRun(IProducer $producer)
		{
			$name = md5(get_class($producer));
			$value = $this->connection->fetchSingle('SELECT [lastrun] FROM [producer] WHERE [name] = %s', $name);

			if ($value === FALSE) {
				return NULL;
			}

			return $value;
		}


		/**
		 * @return void
		 */
		public function setProducerLastRun(IProducer $producer, \DateTime $lastrun)
		{
			$name = md5(get_class($producer));

			try {
				$this->connection->query('INSERT INTO [producer]', array(
					'name' => $name,
					'lastrun' => $lastrun,
				));

			} catch (\DibiDriverException $e) {
				if (substr($e->getMessage(), 0, 15) !== 'Duplicate entry') {
					throw $e;
				}

				$this->connection->query('UPDATE [producer] SET lastrun = %t WHERE [name] = %s', $lastrun, $name);
			}
		}


		/**
		 * @return ChannelInfo
		 */
		public function getChannelInfo(IChannel $channel)
		{
			$lastcheck = $this->connection->fetchSingle('SELECT [lastcheck] FROM [channel] WHERE [id] = %i', $this->getChannelId($channel));
			return new ChannelInfo($lastcheck);
		}


		/**
		 * @return void
		 */
		public function updateChannelInfo(IChannel $channel, \DateTime $lastcheck = NULL)
		{
			$this->connection->query('UPDATE [channel] SET [lastcheck] = %t WHERE [id] = %i', $lastcheck, $this->getChannelId($channel));
		}


		/**
		 * Zkontroluje existenci zaznamu
		 * @param  IChannel
		 * @param  string
		 * @return bool
		 */
		public function existsChannelEntry(IChannel $channel, $entryId)
		{
			$id = $this->connection->fetchSingle(
				'SELECT [id] FROM [channel_entry] WHERE [channel_id] = %i AND [uniq] = %s LIMIT 1',
				$this->getChannelId($channel),
				md5($entryId)
			);

			return $id !== FALSE;
		}


		/**
		 * @param  IChannel
		 * @param  string
		 * @return void
		 */
		public function saveChannelEntry(IChannel $channel, $entryId, \DateTime $date)
		{
			$data = array(
				'channel_id' => $this->getChannelId($channel),
				'uniq' => md5($entryId),
				'date' => $date,
			);

			try {
				$this->connection->query('INSERT INTO [channel_entry]', $data);

			} catch (\DibiDriverException $e) {
				if (substr($e->getMessage(), 0, 15) !== 'Duplicate entry') {
					throw $e;
				}
			}
		}



		private function getChannelId(IChannel $channel)
		{
			$name = $channel->getName();

			if (!isset($this->channels[$name])) {
				$id = $this->connection->fetchSingle('SELECT [id] FROM [channel] WHERE [name] = %s', $channel->getName());

				if ($id === FALSE) { // new channel
					$this->connection->query('INSERT INTO [channel]', array(
						'name' => $channel->getName(),
						'createdAt' => $this->getTime(),
						'lastcheck' => NULL,
					));
					$id = $this->connection->getInsertId();
				}

				$this->channels[$name] = $id;
			}

			return $this->channels[$name];
		}
	}
