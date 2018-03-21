<?php

	namespace Donut\Facebook;


	class FacebookApi
	{
		/** @var string */
		private $accountId;

		/** @var string */
		private $appId;

		/** @var string */
		private $appSecret;

		/** @var string */
		private $userAccessToken;

		/** @var string */
		private $accessToken;

		/** @var \Facebook\Facebook */
		private $facebook;


		public function __construct($accountId, $appId, $appSecret, $userAccessToken)
		{
			$this->accountId = $accountId;
			$this->appId = $appId;
			$this->appSecret = $appSecret;
			$this->userAccessToken = $userAccessToken;
		}


		public function getAccountId()
		{
			return $this->accountId;
		}


		public function getAccessToken()
		{
			if ($this->accessToken === NULL) {
				$facebook = $this->getFacebook();
				$response = $facebook->get('/' . $this->getAccountId() . '?fields=access_token', $this->userAccessToken);
				$body = $response->getDecodedBody();
				$this->accessToken = $body['access_token'];
			}
			return $this->accessToken;
		}


		/**
		 * @return \Facebook\Facebook
		 */
		public function getFacebook()
		{
			if (!$this->facebook) {
				$this->facebook = new \Facebook\Facebook(array(
					'app_id' => $this->appId,
					'app_secret' => $this->appSecret,
				));
			}
			return $this->facebook;
		}
	}
