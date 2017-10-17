<?php

	namespace Donut\Twitter;

	use Donut\Message;
	use Donut\Helpers;


	class Tweet
	{
		/** @var string */
		private $text;

		/** @var string|NULL */
		private $media;


		/**
		 * @param  string
		 * @param  string|NULL
		 */
		public function __construct($text, $media)
		{
			$text = Helpers::stripWhitespace($text);

			if ($text === '') {
				throw new \Donut\InvalidArgumentException('No text of tweet.');
			}

			$this->text = $text;
			$this->media = $media;
		}


		/**
		 * @return string
		 */
		public function getText()
		{
			return $this->text;
		}


		/**
		 * @return string|NULL
		 */
		public function getMedia()
		{
			return $this->media;
		}


		/**
		 * @return array
		 */
		public function toArray()
		{
			return array(
				'text' => $this->text,
				'media' => $this->media,
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
				isset($data['text']) ? $data['text'] : NULL, // TODO: exception
				isset($data['media']) ? $data['media'] : NULL
			);
		}
	}
