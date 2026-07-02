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
# Generate every prerenderable page.
bin/console static-site:generate

# Purge previously generated files first.
bin/console static-site:generate --purge
```

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
