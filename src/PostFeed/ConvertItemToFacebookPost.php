<?php

	namespace Donut\PostFeed;

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

		/** @var string */
		private $enabledGallery;


		/**
		 * @param  string|callback
		 * @param  string
		 * @param  bool
		 */
		public function __construct($mask, $queue, $enabledGallery = FALSE)
		{
			$this->mask = $mask;
			$this->queue = $queue;
			$this->enabledGallery = $enabledGallery;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$item = PostFeedItem::fromArray($message->getData());
			$message = Helpers::formatText($this->mask, array(
				'%TITLE%' => $item->getTitle(),
				'%TEXT%' => $item->getText(),
				'%URL%' => $item->getUrl(),
				'%IMAGE%' => $item->getImage(),
			), $item);

			$itemMeta = $item->getMeta();
			$fbPost = new Facebook\FacebookPost(
				$message,
				$item->getUrl(),
				$item->getImage(),
				($this->enabledGallery && isset($itemMeta['gallery']) && is_array($itemMeta['gallery'])) ? $itemMeta['gallery'] : NULL
			);
			$manager->createMessage($this->queue, $fbPost->toArray());
		}
	}
