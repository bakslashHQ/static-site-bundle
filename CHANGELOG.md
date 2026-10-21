# Changelog

## 0.2.0

- Dispatch `GenerationFinishedEvent` once a run completes, carrying the URIs of the written
  pages.
- Create the directories of a local `storage` as `0755` (before the umask) instead of Flysystem's
  default `0700`, so a web server running as another user can read the generated site. Existing
  directories keep their mode: remove them, or run `chmod -R u=rwX,go=rX` on them once (#3).
- Redefine the `bakslash_static_site.filesystem.visibility` service to change the modes of a
  local `storage`.

## 0.1.0

Initial release. Requires PHP 8.3 and Symfony 7.4 or 8.x.

- Mark controllers with `#[Prerender]`, on a method or on a class to cover every action it
  holds.
- Generate parameterised routes from a `ParamsProviderInterface` service, or from an inline
  list of parameter sets.
- `static-site:generate` writes the site, `static-site:list` previews it; both accept
  repeatable `--route` and `--path` globs.
- Write to a local directory or to any Flysystem operator service, so S3, FTP and the rest
  need no extra code.
- `base_uri` sets the absolute links baked into the pages, and may include a base path.
- `clean_urls` writes HTML pages as `<path>/index.html`, so any server serves clean URLs
  without rewrite rules.
- Optional HTML and XML minification through `sensiolabs/minify-bundle`.
- Redirects are written as static meta-refresh stubs; any other non-2xx response fails the
  page.
- Hook into a run with `PageRenderedEvent`, `PageDumpedEvent` and `PageFailedEvent`.
- Replace the renderer, dumper, URI provider or filesystem by redefining its service id.
