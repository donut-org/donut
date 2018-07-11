<?php

	namespace Donut\RssFeed;

	use Donut\Helpers;
	use Donut\IWorker;
	use Donut\Manager;
	use Donut\Message;
	use Donut\Twitter;


	class ConvertItemToTweet implements IWorker
	{
		/** @var string|callback */
		private $mask;

		/** @var string */
		private $queue;

		/** @var string */
		private $enabledMedia;


		/**
		 * @param  string|callback
		 * @param  string
		 * @param  bool
		 */
		public function __construct($mask, $queue, $enabledMedia = TRUE)
		{
			$this->mask = $mask;
			$this->queue = $queue;
			$this->enabledMedia = $enabledMedia;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$item = RssFeedItem::fromArray($message->getData());
			$tweet = new Twitter\Tweet(
				Helpers::formatText($this->mask, array(
					'%TITLE%' => $item->getTitle(),
					'%TEXT%' => $item->getText(),
					'%URL%' => $item->getUrl(),
					'%IMAGE%' => $item->getImage(),
				), $item),
				$this->enabledMedia ? $item->getImage() : NULL
			);
			$manager->createMessage($this->queue, $tweet->toArray());
		}
	}
