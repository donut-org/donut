<?php

	namespace Donut\RssFeed;

	use Donut\Message;


	class RssFeedItem
	{
		/** @var string|int */
		private $id;

		/** @var string */
		private $title;

		/** @var string */
		private $date;

		/** @var string|NULL */
		private $text;

		/** @var string|NULL */
		private $url;

		/** @var string|NULL */
		private $image;


		/**
		 * @param  string|int
		 * @param  string
		 * @param  string
		 * @param  string|NULL
		 * @param  string|NULL
		 * @param  string|NULL
		 */
		public function __construct($id, $title, $date, $text, $url, $image)
		{
			$this->id = $id;
			$this->title = $title;
			$this->date = $date;
			$this->text = $text;
			$this->url = $url;
			$this->image = $image;
		}


		/**
		 * @return string|int
		 */
		public function getId()
		{
			return $this->id;
		}


		/**
		 * @return string
		 */
		public function getTitle()
		{
			return $this->title;
		}


		/**
		 * @return string
		 */
		public function getDate()
		{
			return $this->date;
		}


		/**
		 * @return string|NULL
		 */
		public function getText()
		{
			return $this->text;
		}


		/**
		 * @return string|NULL
		 */
		public function getUrl()
		{
			return $this->url;
		}


		/**
		 * @return string|NULL
		 */
		public function getImage()
		{
			return $this->image;
		}


		/**
		 * @return array
		 */
		public function toArray()
		{
			return array(
				'id' => $this->getId(),
				'title' => $this->getTitle(),
				'date' => $this->getDate(),
				'text' => $this->getText(),
				'url' => $this->getUrl(),
				'image' => $this->getImage(),
			);
		}


		/**
		 * @param  array
		 * @return self
		 * @throws \RuntimeException
		 */
		public static function fromArray(array $data)
		{
			if (!isset($data['id'], $data['title'], $data['date'])) {
				throw new \RuntimeException("Missing required property");
			}

			return new static(
				$data['id'],
				$data['title'],
				$data['date'],
				self::getData($data, 'text'),
				self::getData($data, 'url'),
				self::getData($data, 'image')
			);
		}


		private static function getData($data, $field)
		{
			return isset($data[$field]) ? $data[$field] : NULL;
		}
	}
