<?php

	namespace Donut\PostFeed;

	use Donut\Helpers;
	use Donut\IWorker;
	use Donut\Manager;
	use Donut\Message;
	use Donut\Instagram;


	class ConvertItemToInstagramPost implements IWorker
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
			$item = PostFeedItem::fromArray($message->getData());
			$instagramPost = new Instagram\InstagramPost(
				Helpers::formatText($this->mask, array(
					'%TITLE%' => $item->getTitle(),
					'%TEXT%' => $item->getText(),
					'%URL%' => $item->getUrl(),
					'%IMAGE%' => $item->getImage(),
				), $item),
				$item->getImage()
			);
			$manager->createMessage($this->queue, $instagramPost->toArray());
		}
	}
