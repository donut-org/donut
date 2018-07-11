<?php

	namespace Donut\RssFeed;

	use Donut\IProducer;
	use Donut\Manager;
	use Donut\Time;
	use Feed;
	use Nette\Http\Url;
	use Nette\Utils\Random;
	use Nette\Utils\Strings;
	use Nette\Utils\Json;
	use Nette\Utils\Validators;


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
			$rss = Feed::loadRss($this->url);
			$baseUrl = new Url($this->url);

			foreach ($rss->item as $item) {
				$postDate = Time::create($item->timestamp);

				if ($postDate->isOlderThan($checkDate)) {
					continue;
				}

				$postId = $this->getItemId($item);

				if ($manager->existsItem($this->url, $postId)) {
					continue;
				}

				$manager->createMessage($this->queue, array(
					'id' => $postId,
					'title' => (string) $item->title,
					'date' => $postDate->getValue(),
					'text' => (string) $item->description,
					'url' => $this->convertToUrl($item, $baseUrl),
					'image' => NULL, // TODO
				), $postDate);
				$manager->saveItem($this->url, $postId);
			}
		}


		private function getItemId($item)
		{
			if (isset($item->guid)) {
				return (string) $item->guid;
			}

			if (isset($content->link)) {
				return (string) $content->link;
			}

			$content = array();

			if (isset($content->title)) {
				$content[] = (string) $content->title;
			}

			if (isset($content->description)) {
				$content[] = (string) $content->description;
			}

			return !empty($content) ? md5(implode("\n", $content)) : Random::generate(20);
		}


		/**
		 * @return string
		 */
		private function convertToText(\SimpleXMLElement $post)
		{
			$content = $post->content;
			$contentType = (string) $content['type'];
			$contentText = (string) $content;

			if ($contentType === 'html') {
				$contentText = strip_tags($contentText);
			}

			return $contentText;
		}


		/**
		 * @return string
		 */
		private function convertToUrl(\SimpleXMLElement $post, Url $baseUrl)
		{
			$url = (string) $post->link;

			if (!Validators::isUrl($url)) {
				$newUrl = new Url;
				$newUrl->setScheme($baseUrl->getScheme());
				$newUrl->setPort($baseUrl->getPort());
				$newUrl->setHost($baseUrl->getHost());
				$newUrl->setUser($baseUrl->getUser());
				$newUrl->setPassword($baseUrl->getPassword());
				$newUrl->setPath($url);
				return (string) $newUrl;
			}

			return $url;
		}
	}
