# StaticSiteBundle

Generate a static site by prerendering your Symfony routes into plain HTML (and
other) files, ready to be served by any static host or CDN.

## How it works

Mark the controllers you want to export as static with the `#[Prerender]`
attribute, then run a single command. The bundle boots the kernel for each
matching route, captures the rendered response and dumps it to disk.

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
    # Where the generated files are written.
    output_dir: '%kernel.project_dir%/public/static-pages'

    # Absolute base URI used to render the pages (scheme + host + base path).
    base_uri: '%env(DEFAULT_URI)%'

    # Base path under which the site is served (e.g. "/my-app"), if any.
    base_path: ''
```

## Usage

### Static routes

```php
use Bakslash\StaticSiteBundle\Prerender;

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

### Dynamic routes

For routes with parameters, point `#[Prerender]` at a `ParamsProviderInterface`
implementation that yields one parameter set per page to generate:

```php
use Bakslash\StaticSiteBundle\ParamsProviderInterface;

final class ArticleParamsProvider implements ParamsProviderInterface
{
    public function __construct(private ArticleRepository $articles)
    {
    }

    public function provideParams(): iterable
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
bin/console ssg:generate

# Only generate URIs matching a regex.
bin/console ssg:generate --filter-pattern='#^/articles/#'

# List what would be generated without writing anything.
bin/console ssg:generate --dry-run
```

## License

Released under the [MIT License](LICENSE).
