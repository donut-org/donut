<?php

	namespace Donut\AtomFeed;

	use Donut\IProducer;
	use Donut\Manager;
	use Donut\Time;
	use Feed;
	use Nette\Http\Url;
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
			$atom = Feed::loadAtom($this->url);
			$baseUrl = new Url($this->url);

			foreach ($atom->entry as $entry) {
				$postDate = Time::create($entry->updated);

				if ($postDate->isOlderThan($checkDate)) {
					continue;
				}

				$postId = (string) $entry->id;

				if ($manager->existsItem($this->url, $postId)) {
					continue;
				}

				$manager->createMessage($this->queue, array(
					'id' => $postId,
					'title' => (string) $entry->title,
					'date' => $postDate->getValue(),
					'text' => $this->convertToText($entry),
					'url' => $this->convertToUrl($entry, $baseUrl),
					'image' => NULL, // TODO
				), $postDate);
				$manager->saveItem($this->url, $postId);
			}
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
			$link = $post->link;
			$url = (string) $link['href']; // resolve URL relative URLs - if (isAbsolute($url) Url('asdf'))

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
