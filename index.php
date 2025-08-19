<?php

require_once './vendor/autoload.php';

use Akrez\HttpProxy\Factories\InlineFactory;
use Akrez\HttpProxy\Senders\CurlSender;

$newRequest = InlineFactory::emitSender(new CurlSender);
