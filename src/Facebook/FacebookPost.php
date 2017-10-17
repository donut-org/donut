<?php

	namespace Donut\Facebook;

	use Donut\Message;


	class FacebookPost
	{
		/** @var string|NULL */
		private $message;

		/** @var string|NULL */
		private $link;

		/** @var string|NULL */
		private $picture;

		/** @var string[]|NULL */
		private $gallery;


		/**
		 * @param  string|NULL
		 * @param  string|NULL
		 * @param  string|NULL
		 * @param  string[]|NULL
		 */
		public function __construct($message, $link, $picture, array $gallery = NULL)
		{
			if ($message === NULL && $link === NULL) {
				throw new \Donut\InvalidArgumentException('Musi byt uvedena alespon $message, nebo $link.');
			}

			$this->message = $message;
			$this->link = $link;
			$this->picture = $picture;
			$this->gallery = $gallery;
		}


		/**
		 * @return string|NULL
		 */
		public function getMessage()
		{
			return $this->message;
		}


		/**
		 * @return string|NULL
		 */
		public function getLink()
		{
			return $this->link;
		}


		/**
		 * @return string|NULL
		 */
		public function getPicture()
		{
			return $this->picture;
		}


		/**
		 * @return string[]|NULL
		 */
		public function getGallery()
		{
			return $this->gallery;
		}


		/**
		 * @return array
		 */
		public function toArray()
		{
			return array(
				'message' => $this->message,
				'link' => $this->link,
				'picture' => $this->picture,
				'gallery' => $this->gallery,
			);
		}


		/**
		 * @param  array
		 * @return static
		 * @throws \RuntimeException
		 */
		public static function fromArray(array $data)
		{
			return new static(
				isset($data['message']) ? $data['message'] : NULL,
				isset($data['link']) ? $data['link'] : NULL,
				isset($data['picture']) ? $data['picture'] : NULL,
				isset($data['gallery']) ? $data['gallery'] : NULL
			);
		}
	}
