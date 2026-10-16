# Troubleshooting

## An entry's image says "Not generated yet"

- **Is it saved?** Images are made from the saved entry, after the save.
- **Is a queue worker running?** With a queue connection other than `sync`, jobs wait for a worker: `php artisan queue:work`. See [Queues](rendering.md#queues).
- **Did the render fail?** Choose **Regenerate** in the entry's panel: it renders there and then and shows the error. Failures are also in `storage/logs`.
- **Is it turned off?** `SOCIAL_IMAGES_ENABLED=false`, or **Generate when an entry is saved** off in the settings.

## "The image couldn't be rendered"

**Render** in the designer and **Regenerate** show the screenshot service's own message.

| Message | |
| --- | --- |
| `The spatie/browsershot package is required` | `composer require spatie/browsershot`, or switch to Cloudflare. See [Rendering](rendering.md). |
| `Could not find Chrome` / `Failed to launch the browser process` | Set `LARAVEL_SCREENSHOT_CHROME_PATH`, or install Puppeteer's Chrome (`npx puppeteer browsers install chrome`). On Linux, try `LARAVEL_SCREENSHOT_NO_SANDBOX=true`. |
| `node: command not found`, `sh: npm: not found` | Set `LARAVEL_SCREENSHOT_NODE_BINARY` and `LARAVEL_SCREENSHOT_NPM_BINARY` to full paths (`which node`). PHP under a web server doesn't see your shell's `PATH`. |
| `Cannot find module 'puppeteer'` | `npm install puppeteer` in your site, or set `LARAVEL_SCREENSHOT_NODE_MODULES_PATH`. |
| `The Cloudflare driver requires both an API token and account ID` | Set `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID`, then `php artisan config:clear`. |
| `Authentication error` (Cloudflare) | The token needs **Account → Browser Rendering → Edit**, for the same account as the ID. |
| `429`, `Rate limit exceeded`, `time limit` | Cloudflare's limit. Wait, or see [Limits](rendering.md#limits). |
| `Waiting for selector html[data-social-ready] failed` | The page never finished: in a code template, a script error, or a picture or font from the network that never arrived. See [Loading nothing](code-templates.md#loading-nothing). |

## The image is missing text or a picture

Text with no value and image layers with no picture are left out, on purpose. Choose the entry under **Preview with** in the designer: layers that would be left out are faded. Check the field's handle in **Insert a field**, and that a picture field holds an image.

## Fonts look wrong

- In the designer, a font only shows once the page has loaded it; give it a moment after choosing one.
- A weight a font doesn't have uses its nearest. The weight menu lists the ones it has.
- An uploaded font needs a file per weight and style.
- In a code template, fonts must come from `{{ social_image:font }}`, not a link to Google Fonts or your site's CSS.

## Social networks show an old image, or none

Networks keep a page's preview for days. Ask them to look again: Facebook's [Sharing Debugger](https://developers.facebook.com/tools/debug/), LinkedIn's [Post Inspector](https://www.linkedin.com/post-inspector/). Check the page's source has `og:image`, and that its address opens.

If the address starts with `http://localhost` or your local domain, set `APP_URL` to your live address.

## The image's address gives a 404

Run `php artisan storage:link`, or check the disk's `url` in `config/filesystems.php`. See [Where images are saved](configuration.md#where-images-are-saved).

## Changed a template, but pages show the old image

Pages keep the image they have until a new one is made: when each entry is next saved, when a page asks for it, or when you choose **Regenerate all**. See [When images change](choosing-templates.md#when-images-change).

## Importing fails

The message says why. "Templates can only be imported from public addresses": the address is on your own network or `localhost`. Download the file and import it from **File** instead.
