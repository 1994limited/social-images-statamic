# PHP API

## An entry's image

```php
use NineteenNinetyFour\SocialImages\Images\Cards;

$cards = app(Cards::class);

$image = $cards->image($entry);            // or $cards->image($entry, 'square')
// ['url' => 'https://…/og-1c2d….png', 'width' => 1200, 'height' => 630, 'alt' => '…', 'source' => 'generated']
// or null when there's no image and no fallback
```

As with the [tags](tags.md#which-image), an image that hasn't been made yet gives the fallback and is queued.

To work with the generated file itself:

```php
$card = $cards->for($entry, 'og');   // null if the entry has no template, or no URL

$card->exists();   // whether the current image has been made
$card->url();
$card->path();     // on the disk, e.g. social-images/{id}/og-{fingerprint}.png
$card->render();   // make it now, through the screenshot service; returns the path
```

`$cards->templateFor($entry)` is the template an entry uses, and `$cards->layoutsFor($entry)` the sizes made when it's saved.

## Queueing images

```php
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;

GenerateImage::dispatch($entry->id(), 'og', force: true);
```

Or `GenerateImage::dispatchWithoutBlocking($entry)`, which on the `sync` queue renders after the response has been sent instead of during it.

## Templates

```php
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Templates\Porter;

$templates = app(TemplateRepository::class);

$templates->all();                 // every template, designer and code
$templates->find('editorial');
$templates->save('editorial', $array);   // checked and normalised first

$porter = app(Porter::class);
$json = json_encode($porter->export($templates->find('editorial')));
$template = $porter->import($porter->decode($json));
```

A template that isn't valid throws `NineteenNinetyFour\SocialImages\Templates\InvalidTemplate`, whose message is written for the person importing it.

## Events

| Event | |
| --- | --- |
| `NineteenNinetyFour\SocialImages\Events\TemplateSaved` | A designer template was saved or imported. `$event->template`. |
| `NineteenNinetyFour\SocialImages\Events\TemplateDeleted` | A designer template was deleted. |

For example, to remake every image when a template changes (mind your rendering service's limits):

```php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\SocialImages\Events\TemplateSaved;

Event::listen(TemplateSaved::class, fn () => Artisan::queue('social-images:generate', ['--queue' => true]));
```

## Rendering another way

The screenshot is taken by `NineteenNinetyFour\SocialImages\Images\Camera`, which takes the page's HTML and a size and returns PNG bytes. To use another service, bind your own class in a service provider:

```php
$this->app->bind(\NineteenNinetyFour\SocialImages\Images\Camera::class, MyCamera::class);
```

Wait for `html[data-social-ready]` before taking the picture.
