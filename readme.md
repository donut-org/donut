
# Donut

[![Build Status](https://travis-ci.org/donut-org/donut.svg?branch=master)](https://travis-ci.org/donut-org/donut)

<a href="https://www.janpecha.cz/donate/"><img src="https://buymecoffee.intm.org/img/donate-banner.v1.svg" alt="Donate" height="100"></a>


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
$facebookQueue = $processor->createQueue('facebook-queue')
	->facebookPublishFacebookPost($accountId, $appId, $appSecret, $userAccessToken);

$processor->createQueue('blogposts-queue')
	->rssFeedFetchNewItems('https://example.com/feed/rss', '1h')
	->rssFeedConvertItemToFacebookPost('NEW BLOGPOST! %TITLE%', $facebookQueue);


// RUN!
$processor->run(100); // number of repeats
```

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
