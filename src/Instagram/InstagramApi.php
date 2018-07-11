<?php

	namespace Donut\Instagram;


	class InstagramApi
	{
		/** @var string */
		private $username;

		/** @var string */
		private $password;

		/** @var string|NULL */
		private $tempDirectory;

		/** @var \InstagramAPI\Instagram */
		private $instagram;


		public function __construct($username, $password)
		{
			$this->username = $username;
			$this->password = $password;
		}


		/**
		 * @param  string|NULL
		 * @return static
		 */
		public function setTempDirectory($tempDirectory)
		{
			$this->tempDirectory = $tempDirectory;
			return $this;
		}


		/**
		 * @return \InstagramAPI\Instagram
		 */
		public function getInstagram()
		{
			if (!$this->instagram) {
				$this->instagram = new \InstagramAPI\Instagram(
					FALSE,
					FALSE,
					isset($this->tempDirectory) ? array('basefolder' => $this->tempDirectory) : array()
				);
				$this->instagram->login($this->username, $this->password);
			}
			return $this->instagram;
		}
	}
