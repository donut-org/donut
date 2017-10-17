<?php

	namespace Donut\Instagram;


	class InstagramApi
	{
		/** @var string */
		private $username;

		/** @var string */
		private $password;

		/** @var \InstagramAPI\Instagram */
		private $instagram;


		public function __construct($username, $password)
		{
			$this->username = $username;
			$this->password = $password;
		}


		/**
		 * @return \InstagramAPI\Instagram
		 */
		public function getInstagram()
		{
			if (!$this->instagram) {
				$this->instagram = new \InstagramAPI\Instagram;
				$this->instagram->login($this->username, $this->password);
			}
			return $this->instagram;
		}
	}
