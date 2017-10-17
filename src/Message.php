<?php

	namespace Donut;

	use Ramsey\Uuid\UuidInterface;


	class Message
	{
		const STATUS_NEW = 0;
		const STATUS_DONE = 1;
		const STATUS_FAILED = 2;

		/** @var UuidInterface */
		private $id;

		/** @var string */
		private $queue;

		/** @var array */
		private $data;

		/** @var Time */
		private $date;

		/** @var Time */
		private $created;

		/** @var int */
		private $status;

		/** @var Time|NULL */
		private $processed;


		public function __construct(UuidInterface $id, $queue, array $data, Time $date, Time $created, $status = self::STATUS_NEW, Time $processed = NULL)
		{
			$this->id = $id;
			$this->queue = $queue;
			$this->data = $data;
			$this->date = $date;
			$this->created = $created;
			$this->status = $status;
			$this->processed = $processed;
		}


		/**
		 * @return UuidInterface
		 */
		public function getId()
		{
			return $this->id;
		}


		/**
		 * @return string
		 */
		public function getQueue()
		{
			return $this->queue;
		}


		/**
		 * @return array
		 */
		public function getData()
		{
			return $this->data;
		}


		/**
		 * @return Time
		 */
		public function getDate()
		{
			return $this->date;
		}


		/**
		 * @return Time
		 */
		public function getCreated()
		{
			return $this->created;
		}


		/**
		 * @return int
		 */
		public function getStatus()
		{
			return $this->status;
		}


		/**
		 * @return Time|NULL
		 */
		public function getProcessed()
		{
			return $this->processed;
		}
	}
