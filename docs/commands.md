# Commands

## Generating images

```bash
php please social-images:generate
```

Makes every image that doesn't exist yet, one at a time, and lists each as it lands. Images that are already current are skipped, so it's safe to run again (for instance after a deploy, or when a run was stopped).

| Option | |
| --- | --- |
| `--collection=journal` | Only entries in this collection. Repeat it for more. |
| `--entry=ID` | Only this entry. Repeat it for more. |
| `--site=en` | Only entries in this site. |
| `--layout=square` | Only this size. Without it, each entry's sizes: Open Graph and any its rule ticks. |
| `--force` | Remake images that are already current. |
| `--queue` | Push each render to the queue instead of rendering here. |

When the screenshot service turns a render away for going too fast, the command waits (as long as the service asks, or `rate_limit_wait` seconds, longer each time) and tries again. After `rate_limit_attempts` refusals in a row it stops and says so: usually a daily allowance has run out. See [Limits](rendering.md#limits) and [Configuration](configuration.md#rate-limits).

It exits with an error if any image failed, so it can run in a deploy script.

## Checking rendering

```bash
php please social-images:doctor
```

Renders a small test image with the renderer in use and says whether it worked, how long it took, and if not, what to change. It also says whether the renderer runs on your server or sends pages to another service.

| Option | |
| --- | --- |
| `--driver=cloudflare` | Check this renderer instead, before switching to it. |

It exits with an error if the render failed, so it can run after a deploy.
