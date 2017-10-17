<?php

	namespace Donut;

	use CzProject\Logger;


	class Processor
	{
		/** @var Logger\ILogger */
		private $logger;

		/** @var IAdapter */
		private $adapter;

		/** @var ICurrentTimeFactory */
		private $currentTimeFactory;

		/** @var Manager */
		private $manager;

		/** @var callback|NULL */
		private $noMessageHandler;

		/** @var ProducerInfo[] */
		private $producers = array();

		/** @var array  [queue => IWorker[]] */
		private $workers;


		/**
		 * @param  IAdapter
		 * @param  callback|NULL
		 */
		public function __construct(IAdapter $adapter, $noMessageHandler = NULL, Logger\ILogger $logger = NULL)
		{
			$this->logger = $logger ? $logger : new Logger\OutputLogger(Logger\ILogger::INFO);
			$this->adapter = $adapter;
			$this->currentTimeFactory = new DefaultCurrentTimeFactory;
			$this->manager = new Manager($adapter, $this->currentTimeFactory, $this->logger);
			$this->noMessageHandler = $noMessageHandler;
		}


		/**
		 * @return static
		 */
		public function addProducer(IProducer $producer, Period $period = NULL)
		{
			$this->producers[] = new ProducerInfo($producer, $period);
			return $this;
		}


		/**
		 * @return static
		 */
		public function addWorker($queue, IWorker $worker)
		{
			$this->workers[$queue][] = $worker;
			return $this;
		}


		/**
		 * @return void
		 */
		public function run($cycles = 1)
		{
			while ($cycles) {
				$this->processProducers();
				$this->processMessage();

				if (is_int($cycles)) {
					$cycles--;
				}
			}
		}


		private function log($subject, $text = NULL, Message $message = NULL)
		{
			$this->adapter->log($subject, $text, $message, $this->currentTimeFactory->createTime());
		}


		private function processProducers()
		{
			$producer = $this->getProducerToRun();

			if ($producer) {
				try {
					$this->logger->log('Run producer ' . $producer->getUniqueId());
					$producer->run($this->manager, $this->adapter->getProducerLastRun($producer));
					$this->logger->log('Producer done.');

				} catch (\Exception $e) {
					$this->logger->log('Producer failed.');
					$msg = array(
						$e->getMessage(),
						'in file ' . $e->getFile(),
						'on line' . $e->getLine(),
						'-----------------------------',
						$e->getTraceAsString(),
					);
					$this->log('Producer ' . get_class($producer) . ' failed', implode("\n", $msg));
				}

				$this->adapter->saveProducerLastRun($producer, $this->currentTimeFactory->createTime());
			}
		}


		/**
		 * @return IProducer|NULL
		 */
		private function getProducerToRun()
		{
			$currentTime = $this->currentTimeFactory->createTime();

			foreach ($this->producers as $info) {
				$producer = $info->producer;
				$period = $info->period;
				$lastrun = $this->adapter->getProducerLastRun($producer);

				if ($this->canBeRun($currentTime, $lastrun, $period)) { // bereme prvni vyhovujici
					return $producer;
				}
			}

			return NULL;
		}


		private function canBeRun(Time $currentTime, Time $lastrun = NULL, Period $period = NULL)
		{
			if ($lastrun === NULL) {
				return TRUE;
			}

			if ($currentTime->isOlderThan($lastrun)) {
				return FALSE; // preskocime ty, ktere byly tuto minutu uz spusteny, nebo byly spusteny v budoucnosti
			}

			if ($period === NULL) {
				return TRUE;
			}

			$interval = (int) $period->getInterval();

			if (!$interval) { // nula => spustime hned
				return TRUE;
			}

			return $lastrun->getMinutesTo($currentTime) >= $interval;
		}


		private function processMessage()
		{
			$message = $this->adapter->fetchMessage();

			if (!$message) {
				$this->logger->log('No message to process.');
				if ($this->noMessageHandler !== NULL) {
					call_user_func($this->noMessageHandler);
				}
				return;
			}

			$queue = $message->getQueue();
			$messageFail = TRUE;
			$hasWorkers = FALSE;
			$this->logger->log('Process message ' . $message->getId()->toString() . ' in queue ' . $queue);

			if (!empty($this->workers[$queue])) {
				$messageFail = FALSE;
				$hasWorkers = TRUE;
				$workers = $this->workers[$queue];

				foreach ($workers as $worker) {
					try {
						$worker->processMessage($message, $this->manager);

					} catch (\Exception $e) {
						$msg = array(
							$e->getMessage(),
							'in file ' . $e->getFile(),
							'on line' . $e->getLine(),
							'-----------------------------',
							$e->getTraceAsString(),
						);
						$this->log("Worker " . get_class($worker) . " for '$queue' failed", implode("\n", $msg), $message);
						$messageFail = TRUE;
					}
				}
			}

			if (!$hasWorkers) {
				$this->logger->log("Queue '$queue' has no workers.");
				$this->log("Queue '$queue' has no workers.");
			}

			if ($messageFail) {
				$this->logger->log('Message processing failed.');
				$this->log('Message processing failed', NULL, $message);
				$this->adapter->markAsFailed($message, $this->currentTimeFactory->createTime());

			} else {
				$this->logger->log('Message processing done.');
				$this->adapter->markAsDone($message, $this->currentTimeFactory->createTime());
			}
		}
	}
