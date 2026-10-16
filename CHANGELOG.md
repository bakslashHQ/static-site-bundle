# Changelog

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
