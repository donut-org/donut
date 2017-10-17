<?php

	namespace Donut\Instagram;

	use Donut\IWorker;
	use Donut\Message;
	use Donut\Manager;


	class PublishInstagramPost implements IWorker
	{
		/** @var InstagramApi */
		private $instagramApi;


		public function __construct(InstagramApi $instagramApi)
		{
			$this->instagramApi = $instagramApi;
		}


		/**
		 * @return void
		 */
		public function processMessage(Message $message, Manager $manager)
		{
			$instagram = $this->instagramApi->getInstagram();
			$instagramPost = InstagramPost::fromArray($message->getData());

			// resize image
			$tmpPhoto = tempnam(sys_get_temp_dir(), 'instagram-photo');
			file_put_contents($tmpPhoto, file_get_contents($instagramPost->getPhoto()));
			$resizer = new \InstagramAPI\MediaAutoResizer($tmpPhoto);

			// upload
			$instagram->timeline->uploadPhoto($resizer->getFile(), array(
				'caption' => $instagramPost->getText(),
			));
			$resizer->deleteFile();
		}
	}
