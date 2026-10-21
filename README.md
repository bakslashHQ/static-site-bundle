# StaticSiteBundle

Generate a static site by prerendering your Symfony routes into plain HTML (and other) files,
ready to be served by any static host or CDN.

Read [Introducing StaticSiteBundle](https://baksla.sh/blog/introducing-static-site-bundle) for the motivation behind it.

## Installation

```bash
composer require bakslash/static-site-bundle
```

If you don't use Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Bakslash\StaticSiteBundle\BakslashStaticSiteBundle::class => ['all' => true],
];
```

## Usage

Mark the controllers you want to export with `#[Prerender]`:

```php
use Bakslash\StaticSiteBundle\Attribute\Prerender;

final class ViewHomepage
{
    #[Prerender]
    #[Route('/', name: 'homepage')]
    public function __invoke(): Response
    {
        // ...
    }
}
```

On a class, it marks every action it holds; a method-level attribute overrides it.

For a controller you don't own, such as `TemplateController`, mark the route where you declare
it instead:

```php
$routes->add('legal_notices', '/legal-notices')
    ->controller(TemplateController::class)
    ->defaults([
        'template' => 'pages/legal_notices.html.twig',
        Prerender::ROUTE_DEFAULTS_KEY => true,
    ]);
```

### Routes with parameters

Point `#[Prerender]` at a `ParamsProviderInterface` implementation that yields one parameter
set per page to generate:

```php
use Bakslash\StaticSiteBundle\ParamsProvider\ParamsProviderInterface;

final class ArticleParamsProvider implements ParamsProviderInterface
{
    public function __construct(private ArticleRepository $articles)
    {
    }

    public function provideParameterSets(): iterable
    {
        foreach ($this->articles->findAll() as $article) {
            yield ['slug' => $article->slug];
        }
    }
}
```

```php
#[Prerender(params: ArticleParamsProvider::class)]
#[Route('/articles/{slug}', name: 'article')]
public function __invoke(string $slug): Response
{
    // ...
}
```

Short lists can be inlined:
`#[Prerender(params: [['slug' => 'first'], ['slug' => 'second']])]`.

### Generating the site

```bash
bin/console static-site:generate --env=prod
```

A kernel in debug mode injects the web debug toolbar into every page, so the command refuses
to run there unless you pass `--allow-debug`. Pass `-v` to list each page as it is written; it
exits non-zero if any page failed.

Both commands take repeatable `--route` and `--path` globs, so you can regenerate part of the
site, or preview what would be written without writing it:

```bash
bin/console static-site:generate --env=prod --route='blog_*' --path='/blog/*'
bin/console static-site:list
```

The command **only writes pages, and never deletes anything.** When you remove or rename a
route, its previously generated file stays behind, and generation is not atomic. Publish the
output rather than pointing a web server at `storage` during a build, and let the deploy step
prune:

```bash
rsync -a --delete build/ user@host:/var/www/site/
aws s3 sync build/ s3://my-bucket --delete
```

Pages are rendered one at a time, and Symfony's stateful services are reset between them
(`kernel.reset`, e.g. Doctrine's identity map), so make sure your own stateful services
implement `Symfony\Contracts\Service\ResetInterface`. `kernel.terminate` never runs, so work
deferred to it (emails, Messenger dispatch, ...) does not fire during generation.

## Configuration

```yaml
# config/packages/bakslash_static_site.yaml
bakslash_static_site:
    # A local directory (prefer an absolute path), or a Flysystem operator service ("@id").
    storage: '%kernel.project_dir%/build'

    # The absolute URL the site is served from, used for the links baked into the pages
    # (canonical, og:, sitemaps, feeds). May include a base path, e.g.
    # "https://example.com/docs". Defaults to framework.router.default_uri.
    base_uri: '%env(DEFAULT_URI)%'

    # Minify generated HTML and XML pages. Requires sensiolabs/minify-bundle.
    minify: false

    # Write HTML pages as "<path>/index.html" instead of "<path>.html", so any server
    # serves clean URLs without rewrite rules.
    clean_urls: false

    # Write the headers that describe each page (Content-Security-Policy,
    # Strict-Transport-Security, ...) to this file of the output, in the "_headers"
    # format of Cloudflare Pages and Netlify. Disabled when null.
    headers_file: null
```

To write somewhere other than the local filesystem, point `storage` at a Flysystem operator
you configured yourself, for instance with
[`league/flysystem-bundle`](https://github.com/thephpleague/flysystem-bundle):

```yaml
bakslash_static_site:
    storage: '@flysystem.storage.static_site'
```

A local `storage` directory and its subdirectories are created as `0755` (before the umask is
applied), so a web server running as another user can read them. For other modes, redefine the
`bakslash_static_site.filesystem.visibility` service:

```yaml
# config/services.yaml
services:
    bakslash_static_site.filesystem.visibility:
        class: League\Flysystem\UnixVisibility\PortableVisibilityConverter
        arguments:
            $directoryPublic: 0o775
            $defaultForDirectories: public
```

## Serving the generated site

Pages are written as `<path>.<format>`, where the format comes from the route's `_format`, or
from the response `Content-Type`, and falls back to HTML. So `/articles/{slug}` becomes
`articles/<slug>.html` and an XML feed at `/feed` becomes `feed.xml`. A path ending in a slash
gets `index` appended, so `/` becomes `index.html`.

With `clean_urls: true`, HTML pages are written as `<path>/index.html` instead, which every
server resolves out of the box, so the rewrite rules below become unnecessary. Other formats,
and paths that already end in their extension, are written as plain files either way.

A route returning a redirect (3xx) is written as a meta-refresh stub pointing at its
`Location`, so canonical redirects work on any host. Only http(s) and site-relative targets
are written; anything else fails the page, as does any other non-2xx response. Because of the
redirect handling, don't prerender authenticated routes: an unauthenticated generation request
would capture their login redirect.

For a custom error page, prerender a `/404.html` route returning a 200 response with your
error markup (the server sets the actual status). Giving the route the `.html` extension keeps
it a file rather than a directory under `clean_urls`, which is what the configurations below,
GitHub Pages and Cloudflare Pages all look for.

### Caddy

```caddyfile
example.com {
    root * /var/www/site
    encode zstd gzip
    try_files {path} {path}.html {path}/index.html
    file_server

    # The headers your application sends (see "Security headers" below).
    header {
        Content-Security-Policy "default-src 'self'"
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
        X-Content-Type-Options nosniff
    }

    handle_errors {
        rewrite * /404.html
        file_server
    }
}
```

### nginx

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/site;

    # The headers your application sends (see "Security headers" below). "always" also
    # sends them with error pages.
    add_header Content-Security-Policy "default-src 'self'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff always;

    location / {
        try_files $uri $uri.html $uri/index.html =404;
    }

    error_page 404 /404.html;
}
```

### Apache

```apache
# .htaccess at the document root
DirectoryIndex index.html
ErrorDocument 404 /404.html

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}.html -f
RewriteRule ^ %{REQUEST_URI}.html [L]
```

### GitHub Pages and Cloudflare Pages

Both already serve `foo.html` at `/foo`, `index.html` for directories and `404.html` for
missing pages, so no configuration is needed. On GitHub Pages, add an empty `.nojekyll` file
so the output is served verbatim:

```yaml
# .github/workflows/deploy.yml (in a job with pages: write + id-token: write)
- run: bin/console static-site:generate --env=prod
- run: touch build/.nojekyll
- uses: actions/upload-pages-artifact@v5
  with:
      path: build
- uses: actions/deploy-pages@v5
```

On Cloudflare Pages, deploy the directory with `npx wrangler pages deploy build`, or point a
Git integration's output directory at it. `_redirects` and `_headers` files placed in the
output are honoured.

### Security headers

A rendered page keeps every header of the response it came from, in `RenderedPage::$headers`,
so listeners can read them. Those that describe the page itself (`Content-Security-Policy`
and its report-only variant, `Strict-Transport-Security`, `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, the three `Cross-Origin-*`
policies, `X-Robots-Tag` and `Link`) also hold for its static copy, but a static host only
sends them if it is told to. `Set-Cookie`, `Cache-Control`, `Vary`, `ETag` and the like
describe one dynamic response, and are never written out.

Set `headers_file` and the command writes them in the `_headers` format of Cloudflare Pages
and Netlify:

```yaml
bakslash_static_site:
    headers_file: '_headers'
```

```
# Written by static-site:generate. Edit outside these lines.
/*
  Content-Security-Policy: default-src 'self'
  Strict-Transport-Security: max-age=31536000; includeSubDomains
/blog
  X-Robots-Tag: noindex
# /about (the /* rule only)
# End of static-site:generate.
```

Cloudflare Pages reads at most 100 rules, so what every page sends with the same value goes
into a single `/*` rule, and a page gets a rule of its own only for what differs. The rules
live between the two comment lines: anything written outside them is kept as it is, and a run
filtered with `--route` or `--path` updates its pages among those already listed there.

On a server you configure, set the same headers in its configuration, as in the Caddy and
nginx examples above. GitHub Pages cannot send custom headers at all: a
`<meta http-equiv="Content-Security-Policy">` tag in your layout is the only fallback,
browsers ignore `frame-ancestors`, `report-uri` and `sandbox` there, and HSTS cannot be set
that way.

Two things to check before relying on the written headers:

- **A nonce-based CSP is frozen at build time.** The nonce in the generated page, and in the
  header written for it, is the one of that single render, served to every visitor: anyone can
  read it and use it. For prerendered pages, allow inline scripts and styles by their hash
  (`'sha256-…'`) instead.
- **A debug kernel asks not to be indexed.** FrameworkBundle adds `X-Robots-Tag: noindex` to
  every response when `kernel.debug` is on, so a site generated with `--allow-debug` writes it
  too. Generate with `--env=prod`.

## Events

Hook into a run with a normal listener or subscriber:

| Event | When | Typical use |
| --- | --- | --- |
| `Event\PageRenderedEvent` | after a page renders, before it is written | transform the page with `$event->setPage(...)` |
| `Event\PageDumpedEvent` | after a page is written | collect the generated URIs, e.g. a build manifest |
| `Event\PageFailedEvent` | a page could not be generated | log or notify; covers URI, render and write failures |
| `Event\GenerationFinishedEvent` | once every page has been processed, even when some failed; not on an aborted run | write a sitemap from `$event->uris`, which leaves out failed pages and, with `--route`/`--path`, the pages outside them |

```php
use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class RewriteLinks
{
    public function __invoke(PageRenderedEvent $event): void
    {
        $page = $event->getPage();
        // withContent() keeps the format and the response headers of the page.
        $event->setPage($page->withContent(
            str_replace('https://staging.example.com', 'https://example.com', $page->content),
        ));
    }
}
```

## Replacing a moving part

Each step is a service behind an interface. Redefine its id in your application configuration,
which wins over the bundle's definition:

```yaml
# config/services.yaml
services:
    # implements Bakslash\StaticSiteBundle\Dumper\DumperInterface
    bakslash_static_site.dumper:
        class: App\StaticSite\MyDumper
```

| Service id | Interface |
| --- | --- |
| `bakslash_static_site.uri_provider` | `UriProvider\UriProviderInterface` |
| `bakslash_static_site.renderer` | `Renderer\RendererInterface` |
| `bakslash_static_site.dumper` | `Dumper\DumperInterface` |
| `bakslash_static_site.filesystem` | Flysystem's `FilesystemOperator` |
| `bakslash_static_site.filesystem.visibility` | Flysystem's `UnixVisibility\VisibilityConverter` (local `storage` only) |

To fail a single page and let the run continue, throw `Exception\RenderingException` from a
renderer or `Exception\DumpingException` from a dumper, and yield a
`UriProvider\UriGenerationFailure` from a URI provider. Any other exception aborts the run.
Everything the bundle throws implements `Exception\ExceptionInterface`.

Minification runs as a `PageRenderedEvent` listener, so it applies to whatever the renderer
produces, including a custom one. The generator driving these is internal:
`static-site:generate` is the entry point, and the events above are how you hook into a run.

## License

Released under the [MIT License](LICENSE).
