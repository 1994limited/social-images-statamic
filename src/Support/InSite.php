<?php

namespace NineteenNinetyFour\SocialImages\Support;

use Carbon\Carbon;
use Closure;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;

/**
 * Runs something as if on the entry's site: its current site, app locale
 * and date language. A queue worker or the Control Panel would otherwise
 * use their own, so a French entry's {date} would differ between the page
 * that asked for its image and the worker making it.
 */
class InSite
{
    public static function run(?Entry $entry, Closure $callback): mixed
    {
        if (! $entry) {
            return $callback();
        }

        $site = $entry->site();
        $previous = [Site::current()->handle(), app()->getLocale(), Carbon::getLocale()];

        Site::setCurrent($site->handle());
        app()->setLocale($site->lang());
        Carbon::setLocale($site->locale());

        try {
            return $callback();
        } finally {
            Site::setCurrent($previous[0]);
            app()->setLocale($previous[1]);
            Carbon::setLocale($previous[2]);
        }
    }
}
