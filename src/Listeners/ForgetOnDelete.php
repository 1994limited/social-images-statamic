<?php

namespace NineteenNinetyFour\SocialImages\Listeners;

use NineteenNinetyFour\SocialImages\Images\Cards;
use Statamic\Events\EntryDeleted;

/**
 * A deleted entry's images go with it.
 */
class ForgetOnDelete
{
    public function handle(EntryDeleted $event): void
    {
        if ($event->entry->id()) {
            rescue(fn () => app(Cards::class)->forget($event->entry));
        }
    }
}
