<?php

	namespace Donut\Twitter;


	class TwitterApi
	{
		/** @var string */
		private $consumerKey;

		/** @var string */
		private $consumerSecret;

		/** @var string */
		private $accessToken;

		/** @var string */
		private $accessTokenSecret;

		/** @var \Twitter */
		private $twitter;


		public function __construct($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret)
		{
			$this->consumerKey = $consumerKey;
			$this->consumerSecret = $consumerSecret;
			$this->accessToken = $accessToken;
			$this->accessTokenSecret = $accessTokenSecret;
		}


		/**
		 * @return \Twitter
		 */
		public function getTwitter()
		{
			if (!$this->twitter) {
				$this->twitter = new \Twitter(
					$this->consumerKey,
					$this->consumerSecret,
					$this->accessToken,
					$this->accessTokenSecret
				);
			}
			return $this->twitter;
		}
	}
