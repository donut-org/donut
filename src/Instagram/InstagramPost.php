<?php

	namespace Donut\Instagram;

	use Donut\Message;


	class InstagramPost
	{
		/** @var string */
		private $text;

		/** @var string */
		private $photo;


		/**
		 * @param  string
		 * @param  string
		 */
		public function __construct($text, $photo)
		{
			$this->text = $text;
			$this->photo = $photo;
		}


		/**
		 * @return string
		 */
		public function getText()
		{
			return $this->text;
		}


		/**
		 * @return string
		 */
		public function getPhoto()
		{
			return $this->photo;
		}


		/**
		 * @return array
		 */
		public function toArray()
		{
			return array(
				'text' => $this->text,
				'photo' => $this->photo,
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
				isset($data['text']) ? $data['text'] : NULL,
				isset($data['photo']) ? $data['photo'] : NULL
			);
		}
	}
