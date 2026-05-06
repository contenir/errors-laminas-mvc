# contenir/errors-laminas-mvc

Laminas MVC adapter for [`contenir/errors`](https://github.com/contenir/errors).

Replaces the framework's default 4xx/5xx rendering with admin-authored
per-status pages when configured. Non-invasive on first install — when
the admin hasn't authored a page for a given status, the framework's
default rendering proceeds unchanged.

## Install

```bash
composer require contenir/errors-laminas-mvc
```

The Module is auto-registered by `laminas/laminas-component-installer`.

## Configure

Point the package at a shared error-pages file (the same path the admin
writes to) and optionally wire a PSR-3 logger:

```php
// config/autoload/errors.global.php

return [
    'errors' => [
        'file'          => realpath(__DIR__ . '/../..') . '/configs/errors.local.php',
        'view_template' => 'contenir/errors/fault',  // override to use a Site-owned template
        'logger'        => 'log.psr3',                // optional PSR-3 service ID
    ],
];
```

`file` is required. `view_template` defaults to the package's shipped
`contenir/errors/fault.phtml` (uses the `.fault` BEM block, no scripts,
`<meta name="robots" content="noindex">`, single "Return home" link).
`logger` may be `null`, a service ID resolvable from the container, or a
`Psr\Log\LoggerInterface` instance.

## How it works

The `ErrorListener` attaches at `MvcEvent::EVENT_RENDER` and
`EVENT_RENDER_ERROR` at priority `100`. By the time RENDER fires, the
response status is already settled (RouteNotFoundStrategy has set 404,
ExceptionStrategy has set 500, or a controller has called
`setStatusCode(403)`). The listener:

1. Logs the 4xx/5xx via the optional PSR-3 logger (`info()` for 4xx,
   `error()` for 5xx, with the exception in context if available).
2. If the repository has a non-empty page for the status, swaps the
   result `ViewModel` template + variables (`status`, `title`, `body`)
   and marks it terminal so the layout is bypassed.
3. Otherwise leaves the existing render path untouched.

## Override the view

To brand the page beyond what's possible in the body field, set
`errors.view_template` to your own template name:

```php
'errors' => [
    'view_template' => 'site/error-page',
],
```

Your template receives:

| Variable  | Type     | Notes                                               |
| --------- | -------- | --------------------------------------------------- |
| `$status` | `int`    | HTTP status code (e.g. 404)                         |
| `$title`  | `string` | Plain text written by the admin                     |
| `$body`   | `string` | Sanitized HTML fragment (inline only) — render raw  |

The body is *trusted* — sanitization is the writer's responsibility (see
the admin-side wiring in the consuming CMS). Render with `<?= $body ?>`.

## License

MIT