# StaticSiteBundle

Generate a static site by prerendering your Symfony routes into plain HTML (and
other) files, ready to be served by any static host or CDN.

## How it works

Mark the controllers you want to export as static with the `#[Prerender]`
attribute, then run a single command. The bundle boots the kernel for each
matching route, captures the rendered response and writes it to the configured storage.

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

## Configuration

```yaml
# config/packages/bakslash_static_site.yaml
bakslash_static_site:
    # Where the generated site is written: a local directory path, or a Flysystem
    # operator service reference ("@service_id"). See "Storage backend" below.
    storage: '%kernel.project_dir%/build'

    # The absolute URL the generated site is served from, used for the absolute links baked
    # into the pages (canonical, og:, sitemaps, feeds); may include a base path (e.g.
    # "https://example.com/docs"). Defaults to framework.router.default_uri when omitted.
    base_uri: '%env(DEFAULT_URI)%'

    # Minify generated HTML and XML pages (requires sensiolabs/minify-bundle).
    minify: false

    # Write HTML pages as "<path>/index.html" instead of "<path>.html", so the site serves
    # clean URLs with no server rewrite rules (see "Serving the generated site").
    clean_urls: false
```

## Usage

### Static routes

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

`#[Prerender]` can be placed on a controller method or, for invokable
controllers, on the class.

### Dynamic routes

For routes with parameters, point `#[Prerender]` at a `ParamsProviderInterface`
implementation that yields one parameter set per page to generate:

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

You can also pass an inline list of parameter sets:

```php
#[Prerender(params: [['slug' => 'first'], ['slug' => 'second']])]
```

### Generating the site

```bash
bin/console static-site:generate
```

The command **only writes pages, and never deletes anything.** When you remove or rename a
route, its previously generated file stays in `storage`; you are responsible for removing
stale files yourself. The natural place to do that (it also gives you atomic publishing) is
your deploy step, which is built for it:

```bash
rsync -a --delete build/ user@host:/var/www/site/
aws s3 sync build/ s3://my-bucket --delete
```

Generation is not atomic either, so publish the generated output rather than pointing your
web server directly at `storage` during a build.

### Large sites

Pages are rendered one at a time in a single process, and the bundle resets Symfony's
stateful services (`kernel.reset`, e.g. Doctrine's identity map) between each one, so memory
stays flat no matter how many pages you generate. Run generation with `APP_ENV=prod` for the
least overhead, and make sure any stateful service of your own implements
`Symfony\Contracts\Service\ResetInterface` so it is reset between pages too.

## Serving the generated site

Pages are written as `<path>.html`, and `/` as `index.html`. For example the route
`/articles/{slug}` becomes `articles/<slug>.html`. Point your server's document root at the
generated directory and have it serve `foo.html` when `/foo` is requested. For a custom
error page, add a prerendered `/404` route; it is written as `404.html`, which the examples
below reference.

If you set `clean_urls: true`, pages are written as `<path>/index.html` instead, and every
server serves clean URLs out of the box, so the rewrite rules below become unnecessary.

### Caddy

```caddyfile
example.com {
    root * /var/www/site
    encode zstd gzip
    try_files {path} {path}.html {path}/index.html
    file_server

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

### GitHub Pages

GitHub Pages already serves `foo.html` at `/foo`, `index.html` for directories, and
`404.html` for missing pages, so no rewrite rules are needed. Add an empty `.nojekyll` file to
the output so the files are served verbatim, then publish the generated directory (build
steps depend on your app):

```yaml
# .github/workflows/deploy.yml (inside a job with pages: write + id-token: write)
- run: bin/console static-site:generate
- run: touch build/.nojekyll
- uses: actions/upload-pages-artifact@v3
  with:
      path: build
- uses: actions/deploy-pages@v4
```

### Cloudflare Pages

Cloudflare Pages also serves `foo.html` at `/foo`, `index.html` for directories, and
`404.html` automatically, so no rewrite rules are needed. Deploy the generated directory
directly, or point a Git integration's output directory at it:

```bash
npx wrangler pages deploy build --project-name my-site
```

Optional `_redirects` and `_headers` files placed in the output are honoured.

## Custom renderer or dumper

Each moving part is a service with a `bakslash_static_site.`-prefixed id behind an
interface. To swap an implementation, redefine the service id in your application
configuration (which wins over the bundle's definition) with a class implementing
the relevant interface:

```yaml
# config/services.yaml
services:
    # implements Bakslash\StaticSiteBundle\Dumper\DumperInterface
    bakslash_static_site.dumper:
        class: App\StaticSite\MyDumper
        arguments: [...]

    # implements Bakslash\StaticSiteBundle\Renderer\RendererInterface
    bakslash_static_site.renderer:
        class: App\StaticSite\MyRenderer
        arguments: [...]
```

Overridable ids: `bakslash_static_site.renderer`, `bakslash_static_site.dumper`,
`bakslash_static_site.filesystem`, `bakslash_static_site.uri_provider`. When minification
is enabled it still decorates whatever `bakslash_static_site.renderer` resolves to,
including your custom one.

### Storage backend

Pages are written through [Flysystem](https://flysystem.thephpleague.com/). The `storage`
option decides where: a local directory path (the default), or a reference to a Flysystem
operator service you configured yourself, e.g. with
[`league/flysystem-bundle`](https://github.com/thephpleague/flysystem-bundle):

```yaml
bakslash_static_site:
    # write to an S3 bucket via a preconfigured Flysystem service (note the @ and quotes)
    storage: '@flysystem.storage.static_site'
```

Both forms wire the same `bakslash_static_site.filesystem` service. For full control, you
can override `bakslash_static_site.dumper` with your own `DumperInterface` implementation
instead.

## Minification (optional)

Set `minify: true` to minify generated HTML and XML pages. This requires
[`sensiolabs/minify-bundle`](https://github.com/sensiolabs/minify-bundle) to be
installed and enabled; if it isn't, the container build fails with a clear error.

## License

Released under the [MIT License](LICENSE).
