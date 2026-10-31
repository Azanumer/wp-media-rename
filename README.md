# Media Rename

A WordPress plugin that renames uploaded media files automatically, so you never end up with `IMG_4829.JPG` or `Screenshot 2026-10-31 at 10.12.png` in your uploads folder again.

## Features

- Renames on upload via `wp_handle_upload_prefilter` — works in the editor, Media Library and REST uploads
- Configurable pattern under **Settings → Media Rename**, tokens:
  - `{date}` — upload date, e.g. `2026-10-31`
  - `{time}` — upload time, e.g. `101205`
  - `{original}` — sanitized original filename (no extension)
  - `{post_title}` — title of the post being edited, or the original name when uploading from the Media Library
- Extension is always preserved; WordPress still appends `-1`, `-2` on collisions
- Lowercase and space-to-dash options
- `wmr_filename` filter to override the final name from a theme/plugin

## Example

Pattern `{date}-{post_title}` + uploading `photo.jpg` while editing "Autumn in Lahore" → `2026-10-31-autumn-in-lahore.jpg`.

## Notes

- Existing library files are **not** renamed — renaming those would break published URLs.
- Pattern input is restricted to safe characters plus the known tokens.

## License

MIT
