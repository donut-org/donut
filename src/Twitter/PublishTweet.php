<?php

	namespace Donut\Twitter;

	use Donut\IWorker;
	use Donut\Message;
	use Donut\Manager;


	class PublishTweet implements IWorker
	{
		/** @var TwitterApi */
		private $twitterApi;


		public function __construct(TwitterApi $twitterApi)
		{
			$this->twitterApi = $twitterApi;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$twitter = $this->twitterApi->getTwitter();
			$tweet = Tweet::fromArray($message->getData());
			$media = $tweet->getMedia();
			$tmpMedia = NULL;

			if ($media !== NULL) {
				$tmpMedia = tempnam(sys_get_temp_dir(), 'twitter-image');
				file_put_contents($tmpMedia, file_get_contents($media));
			}

			try {
				$twitter->send($tweet->getText(), $tmpMedia);

			} catch (\TwitterException $e) {
				if ($tmpMedia !== NULL) {
					unlink($tmpMedia);
				}

				throw $e;
			}

			if ($tmpMedia !== NULL) {
				unlink($tmpMedia);
			}
		}
	}
