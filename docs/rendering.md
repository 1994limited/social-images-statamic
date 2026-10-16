# Rendering

An image is a screenshot of a web page: Social Images builds the page for an entry (its template, with the entry's text, pictures and fonts inside it) and has a browser take a picture of it. The browser is [spatie/laravel-screenshot](https://github.com/spatie/laravel-screenshot), which can use either of two:

| | Browsershot | Cloudflare Browser Rendering |
| --- | --- | --- |
| What runs | Chrome on your server, through Node.js | Chrome on Cloudflare's servers |
| Server needs | Node.js, the `puppeteer` package, Chrome or Chromium | Nothing |
| Cost | Free | Free plan: 10 minutes of rendering a day (about 200–300 images); paid plans for more |
| Good for | Your own computer, and servers where you can install Chrome | Shared hosting, Laravel Cloud, Forge servers without Chrome |

Each image takes one to three seconds either way.

The page is sent to the browser as HTML, with everything inside it, so the browser never fetches anything from your site. That's why Cloudflare can render images for a site it can't reach, like a local `.test` site.

## Browsershot

Install Browsershot and Puppeteer:

```bash
composer require spatie/browsershot
npm install puppeteer
```

Puppeteer downloads its own Chrome. To use a Chrome you have already, install it with `PUPPETEER_SKIP_DOWNLOAD=1 npm install puppeteer` and give its path below.

Then in `.env`:

```env
LARAVEL_SCREENSHOT_DRIVER=browsershot
LARAVEL_SCREENSHOT_NODE_BINARY=/usr/local/bin/node
LARAVEL_SCREENSHOT_NPM_BINARY=/usr/local/bin/npm
LARAVEL_SCREENSHOT_CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
```

Set the paths even if `node` works in your terminal: PHP run by a web server (Herd, Valet, PHP-FPM) doesn't see your shell's `PATH`. Find them with `which node` and `which npm`. On Linux servers you may also need `LARAVEL_SCREENSHOT_NO_SANDBOX=true`.

## Cloudflare Browser Rendering

1. In the [Cloudflare dashboard](https://dash.cloudflare.com), note your **Account ID** (on the account's home page).
2. Under **My Profile → API Tokens**, create a token with the **Account → Browser Rendering → Edit** permission for that account.
3. In `.env`:

    ```env
    LARAVEL_SCREENSHOT_DRIVER=cloudflare
    CLOUDFLARE_API_TOKEN=your-token
    CLOUDFLARE_ACCOUNT_ID=your-account-id
    ```

Keep the token in `.env` only: never in config files or the repository.

### Limits

Cloudflare's free plan allows a limited amount of rendering a day and a few browsers at once (see [Cloudflare's limits](https://developers.cloudflare.com/browser-rendering/limits/) for today's numbers). When a render is turned away for going too fast:

- **On save**, the queued job tries again after 15 seconds, then a minute, then five.
- **`social-images:generate`** waits and tries again, longer each time, and stops after four refusals in a row, saying the day's allowance has probably run out. Run it again later: it skips the images already made. See [Commands](commands.md).
- **Generate missing / Regenerate all** in the Control Panel does the same, with a countdown on the page.

A site with a few hundred entries fits in a day on the free plan. For more, generate in batches (`--collection`), or use a paid plan.

## Queues

Images are rendered in the background wherever they can be, so nobody waits for one. How depends on whether you run a queue worker (`QUEUE_CONNECTION=redis`, `database` and so on, with `php artisan queue:work` running), or use the `sync` queue, Laravel's default on new sites, which has no worker.

| What starts a render | With a queue worker | On the `sync` queue |
| --- | --- | --- |
| Saving an entry | Queued | Rendered after the response has been sent |
| A page asking for an image that's missing or out of date | Queued | Rendered after the response has been sent |
| Opening an entry in the Control Panel | Queued | Rendered after the response has been sent |
| Saving a template | Queued, for every entry that uses it | Nothing: made on demand, as above, or from the Images page |
| **Regenerate** in an entry, and **Generate** / **Regenerate all** on the Images page | Rendered there and then, one image per request, so you see each land | The same |
| `social-images:generate` | Rendered by the command, one at a time (`--queue` queues them instead) | The same |
| **Regenerate social images** in a collection listing | Queued | Rendered during the action's request |

"After the response has been sent" means the page or save finishes first and the render runs in the same PHP process straight afterwards, so no one waits for it, but it does hold that process for the second or two it takes. A template save on the `sync` queue doesn't render everything at once for that reason: on a big site that could be hundreds of renders in one process.

So a queue worker isn't required, but it's the better choice on a site with many entries, or with Cloudflare's rate limits: queued renders that are turned away for going too fast wait and try again (after 15 seconds, then a minute, then five).

To use a different connection or queue for images only:

```env
SOCIAL_IMAGES_QUEUE_CONNECTION=redis
SOCIAL_IMAGES_QUEUE=images
```

Renders are unique per entry, size and content: saving an entry five times in a minute without changing what its image shows makes nothing; changing it twice makes two renders, and the second replaces the first. A render queued for a version of an entry that has since changed stands down, since the change queued its own.

Nothing is deleted before its replacement exists: pages and the Control Panel keep showing an entry's previous image until the new one is ready.

## Turning it off

```env
SOCIAL_IMAGES_ENABLED=false
```

Nothing is made on save, on page view or in bulk; pages use the [fallback image](choosing-templates.md#the-fallback-image). Images already made are kept and still used. Handy on staging sites that shouldn't spend your Cloudflare allowance.
