<?php

	namespace Donut\AtomFeed;

	use Donut\Helpers;
	use Donut\IWorker;
	use Donut\Manager;
	use Donut\Message;
	use Donut\Facebook;


	class ConvertItemToFacebookPost implements IWorker
	{
		/** @var string|callback */
		private $mask;

		/** @var string */
		private $queue;


		/**
		 * @param  string|callback
		 * @param  string
		 */
		public function __construct($mask, $queue)
		{
			$this->mask = $mask;
			$this->queue = $queue;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$item = AtomFeedItem::fromArray($message->getData());
			$message = Helpers::formatText($this->mask, array(
				'%TITLE%' => $item->getTitle(),
				'%TEXT%' => $item->getText(),
				'%URL%' => $item->getUrl(),
				'%IMAGE%' => $item->getImage(),
			), $item);

			$fbPost = new Facebook\FacebookPost(
				$message,
				$item->getUrl(),
				$item->getImage()
			);
			$manager->createMessage($this->queue, $fbPost->toArray());
		}
	}
