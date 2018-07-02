
# Donut

[![Build Status](https://travis-ci.org/donut-org/donut.svg?branch=master)](https://travis-ci.org/donut-org/donut)

<a href="https://www.patreon.com/bePatron?u=9680759"><img src="https://c5.patreon.com/external/logo/become_a_patron_button.png" alt="Become a Patron!" height="35"></a>
<a href="https://www.paypal.me/janpecha/5eur"><img src="https://buymecoffee.intm.org/img/button-paypal-white.png" alt="Buy me a coffee" height="35"></a>


## Installation

[Download a latest package](https://github.com/donut-org/donut/releases) or use [Composer](http://getcomposer.org/):

```
composer require donut-org/donut
```

Donut requires PHP 5.6.0 or later.


## Usage

``` php
<?php

require __DIR__ . '/vendor/autoload.php';

// init
$adapter = new Donut\Adapters\DibiSqliteAdapter(__DIR__ . '/app/db.sq3');
$processor = new Donut\Processor($adapter, function () {
	sleep(5 * 60); // 5 minutes
});


// prepare tasks
$facebookQueue = Donut\Queue::create($processor, 'facebook-queue')
	->facebookPublishFacebookPost($accountId, $appId, $appSecret, $userAccessToken);

Donut\Queue::create($processor, 'blogposts-queue')
	->postFeedFetchNewItems('https://example.com/feed/posts.json', '1h')
	->postFeedConvertItemToFacebookPost('NEW BLOGPOST! %TITLE%', $facebookQueue);


// RUN!
$processor->run(100); // number of repeats
```

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
