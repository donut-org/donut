<?php

	namespace Donut;


	class Queue
	{
		/** @var Processor */
		private $processor;

		/** @var string */
		private $queue;


		/**
		 * @param  Processor
		 * @param  string
		 */
		public function __construct(Processor $processor, $queue)
		{
			$this->processor = $processor;
			$this->queue = $queue;
		}


		/**
		 * @return string
		 */
		public function getName()
		{
			return $this->queue;
		}


		/**
		 * @return static
		 */
		public function facebookPublishFacebookPost($accountId, $appId, $appSecret, $userAccessToken)
		{
			$this->processor->addWorker(
				$this->queue,
				new Facebook\PublishFacebookPost(new Facebook\FacebookApi($accountId, $appId, $appSecret, $userAccessToken))
			);
			return $this;
		}


		/**
		 * @return static
		 */
		public function instagramPublishInstagramPost($username, $password, $tempDirectory = NULL)
		{
			$instagramApi = new Instagram\InstagramApi($username, $password);
			$instagramApi->setTempDirectory($tempDirectory);

			$this->processor->addWorker(
				$this->queue,
				new Instagram\PublishInstagramPost($instagramApi)
			);
			return $this;
		}


		/**
		 * @param  string
		 * @param  string
		 * @return static
		 */
		public function postFeedFetchNewItems($url, $period)
		{
			$this->processor->addProducer(
				new PostFeed\FetchNewItems($this->queue, $url),
				Period::every($period)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function postFeedConvertItemToInstagramPost($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new PostFeed\ConvertItemToInstagramPost($mask, $this->getQueueName($queue))
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @param  bool
		 * @return static
		 */
		public function postFeedConvertItemToFacebookPost($mask, $queue, $enabledGallery = FALSE)
		{
			$this->processor->addWorker(
				$this->queue,
				new PostFeed\ConvertItemToFacebookPost($mask, $this->getQueueName($queue), $enabledGallery)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function postFeedConvertItemToTweet($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new PostFeed\ConvertItemToTweet($mask, $this->getQueueName($queue))
			);
			return $this;
		}


		/**
		 * @param  string
		 * @param  string
		 * @return static
		 */
		public function atomFeedFetchNewItems($url, $period)
		{
			$this->processor->addProducer(
				new AtomFeed\FetchNewItems($this->queue, $url),
				Period::every($period)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function atomFeedConvertItemToInstagramPost($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new AtomFeed\ConvertItemToInstagramPost($mask, $this->getQueueName($queue))
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @param  bool
		 * @return static
		 */
		public function atomFeedConvertItemToFacebookPost($mask, $queue, $enabledGallery = FALSE)
		{
			$this->processor->addWorker(
				$this->queue,
				new AtomFeed\ConvertItemToFacebookPost($mask, $this->getQueueName($queue), $enabledGallery)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function atomFeedConvertItemToTweet($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new AtomFeed\ConvertItemToTweet($mask, $this->getQueueName($queue))
			);
			return $this;
		}



		/**
		 * @param  string
		 * @param  string
		 * @return static
		 */
		public function rssFeedFetchNewItems($url, $period)
		{
			$this->processor->addProducer(
				new RssFeed\FetchNewItems($this->queue, $url),
				Period::every($period)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function rssFeedConvertItemToInstagramPost($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new RssFeed\ConvertItemToInstagramPost($mask, $this->getQueueName($queue))
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @param  bool
		 * @return static
		 */
		public function rssFeedConvertItemToFacebookPost($mask, $queue, $enabledGallery = FALSE)
		{
			$this->processor->addWorker(
				$this->queue,
				new RssFeed\ConvertItemToFacebookPost($mask, $this->getQueueName($queue), $enabledGallery)
			);
			return $this;
		}


		/**
		 * @param  string|callback
		 * @param  string|Queue
		 * @return static
		 */
		public function rssFeedConvertItemToTweet($mask, $queue)
		{
			$this->processor->addWorker(
				$this->queue,
				new RssFeed\ConvertItemToTweet($mask, $this->getQueueName($queue))
			);
			return $this;
		}


		/**
		 * @return static
		 */
		public function twitterPublishTweet($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret)
		{
			$this->processor->addWorker(
				$this->queue,
				new Twitter\PublishTweet(new Twitter\TwitterApi($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret))
			);
			return $this;
		}


		/**
		 * @param  Queue|string
		 * @return string
		 */
		private function getQueueName($queue)
		{
			if (is_object($queue) && ($queue instanceof Queue)) {
				return $queue->getName();
			}
			return $queue;
		}


		/**
		 * @param  Processor
		 * @param  string
		 * @return static
		 */
		public static function create(Processor $processor, $queue)
		{
			return new static($processor, $queue);
		}
	}
