<?php

namespace NineteenNinetyFour\SocialImages\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\SocialImages\Templates\JsonTemplate;

class TemplateSaved
{
    use Dispatchable;

    public function __construct(public JsonTemplate $template) {}
}
