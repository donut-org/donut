<?php

	namespace Donut\PostFeed;

	use Donut\IProducer;
	use Donut\Manager;
	use Donut\Time;
	use Donut\Helpers;
	use Nette\Utils\Strings;
	use Nette\Utils\Json;


	class FetchNewItems implements IProducer
	{
		/** @var string */
		private $queue;

		/** @var string */
		private $url;


		/**
		 * @param  string
		 * @param  string
		 */
		public function __construct($queue, $url)
		{
			$this->queue = $queue;
			$this->url = $url;
		}


		/**
		 * @return string
		 */
		public function getUniqueId()
		{
			return $this->url;
		}


		/**
		 * @return void
		 */
		public function run(Manager $manager, Time $lastrun = NULL)
		{
			if ($lastrun === NULL) { // nedelame nic - prave ted zadne prispevky nepribyly
				return;
			}

			$checkDate = Time::sub($lastrun, 24 * 60); // akceptujeme prispevky az 24h zpetne
			$content = Helpers::fetchUrl($this->url);
			$posts = Json::decode($content, Json::FORCE_ARRAY);

			foreach ($posts as $post) {
				$postDate = Time::create($post['date']);

				if ($postDate->isOlderThan($checkDate)) {
					continue;
				}

				if ($manager->existsItem($this->url, $post['id'])) {
					continue;
				}

				$manager->createMessage($this->queue, $post, $postDate);
				$manager->saveItem($this->url, $post['id']);
			}
		}
	}
