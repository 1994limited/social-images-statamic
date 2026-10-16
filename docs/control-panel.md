# The Control Panel

Everything is under **Tools → Social Images**.

## Templates

Every template, previewed with sample text, and how many settings rules use it. **Create template** starts one from a starter or blank; **Import** adds one from JSON (see [Importing and exporting](importing.md)). Each designer template's **⋯** menu can edit, duplicate, export or delete it. [Code templates](code-templates.md) are listed too, marked **Code**; they're edited as files.

Deleting a template that rules use leaves their entries with no image until you choose another.

## The designer

See [The designer](designer.md).

## Images

Every entry that has a template, with each of its images and whether it's current. Search by title, or show one collection.

- An image that's out of date (its entry or template changed) shows the image still in use, faded, marked **Out of date**.
- **Generate missing** (or **Update out of date**) makes the images that don't exist yet or are out of date.
- **Regenerate all** remakes them all, even current ones. Use it after changing a template, so you don't wait for each entry to be saved.
- The ↻ beside an image remakes just that one.

Runs go one image at a time from the page, so you can watch them land and **Stop** at any point. If the screenshot service says it's busy, the run waits and tries again, with a countdown; if it keeps refusing, it stops and says so (see [Limits](rendering.md#limits)). Images already made are kept.

## Settings

**Tools → Addons → Social Images → Settings**, or **Settings** on the Templates page: which template entries use, extra sizes, generating on save, the fallback image, and SEO Pro. See [Choosing templates](choosing-templates.md) and [SEO Pro](seo.md).

## In an entry

Add the **Social image** fieldset to a blueprint (see [Installation](installation.md#first-steps)) and each entry's sidebar shows:

- **The image**, as it is now. Click it to open it full size. When it's missing or out of date (the entry or its template changed), opening the entry starts making it, and the panel shows the previous image (if there is one) with where it's got to: **Waiting to start…** while it's queued, then **Rendering…** with a count of seconds. It swaps in the new image when it's ready, usually in a few seconds.
- **Regenerate**, to remake it now (for instance after replacing a picture's file in place).
- **Template**: leave it empty to use the collection's, or choose another, or **No image**.
- **Use this image instead**: an image of your own to share for this entry.

The preview shows the saved entry: save to see changes.

## In collection listings

Select entries and choose **Regenerate social images** from the actions to remake theirs in the background.

## Permissions

With Statamic Pro, under **Users → Permissions**:

- **View social images and templates**: the Templates and Images pages, and each entry's image.
  - **Design, import, export and delete templates, and generate any entry's image**: the designer (and its real renders), fonts and uploads, importing and exporting, and remaking any entry's image.

Previewing a template with an entry shows that entry's values, so the designer only offers (and only accepts) entries the user can view.

Anyone who can edit an entry can remake its image, from the entry or the Images page. Settings need **Configure addons**, as for any addon.
