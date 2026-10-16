# The demo

Dog-ear Books is a made-up independent bookshop, built to show Social Images: a catalogue of twelve books (whose images use a [code template](code-templates.md) showing each cover) and a journal of posts with writers, dates and pictures (using the Editorial designer template, in Open Graph and square). The screenshots in these docs are from it.

It's in the [social-images-statamic-demo](https://github.com/1994limited/social-images-statamic-demo) repository and runs locally, with no licence needed.

```bash
git clone https://github.com/1994limited/social-images-statamic-demo.git
cd social-images-statamic-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
npm install
php please demo:users
php please demo:content
php artisan serve
```

Then sign in to the Control Panel at `/cp` as `admin@example.com` with the password `password`. The demo accounts are made only on local sites (`APP_ENV=local`), since their password is known.

The demo uses Browsershot with the Chrome Puppeteer installs; see the demo's README to use your own Chrome or Cloudflare instead. Make the images with **Tools → Social Images → Images → Generate missing**, or `php please social-images:generate`.
