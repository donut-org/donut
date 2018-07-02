<?php

	namespace Donut\Facebook;

	use Donut\IWorker;
	use Donut\Message;
	use Donut\Manager;


	class PublishFacebookPost implements IWorker
	{
		/** @var FacebookApi */
		private $facebookApi;


		public function __construct(FacebookApi $facebookApi)
		{
			$this->facebookApi = $facebookApi;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$fbPost = FacebookPost::fromArray($message->getData());
			$gallery = $fbPost->getGallery();
			$data = array();

			if ($fbPost->getMessage() !== NULL) {
				$data['message'] = $fbPost->getMessage();
			}

			if ($fbPost->getLink() !== NULL) {
				$data['link'] = $fbPost->getLink();

				if ($fbPost->getGallery() !== NULL) {
					$limit = 5;
					foreach ($fbPost->getGallery() as $galleryPhoto) {
						$data['child_attachments'][] = array(
							'link' => $fbPost->getLink(),
							'picture' => $galleryPhoto,
						);

						$limit--;

						if ($limit < 1) {
							break;
						}
					}

					if (isset($data['child_attachments']) && count($data['child_attachments']) < 2) {
						unset($data['child_attachments']);
					}
				}
			}

			if (empty($data)) {
				throw new \Donut\InvalidStateException('Empty fbPost, missing message and link');
			}

			if ($fbPost->getPicture() !== NULL) {
				// $data['picture'] = $fbPost->getPicture();
			}

			$data['access_token'] = $this->facebookApi->getAccessToken();
			$fbNode = '/' . $this->facebookApi->getAccountId() . '/feed';

			$facebook = $this->facebookApi->getFacebook();
			$facebook->post($fbNode, $data);
		}
	}
