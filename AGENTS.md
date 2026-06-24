# Concept Core 2 — інструкції для агента

> Цей repo — **тонке ядро**. Повний контекст (skeleton, extensions, symlink) — у
> [`/var/www/concept-skeleton-dev-2/AGENTS.md`](/var/www/concept-skeleton-dev-2/AGENTS.md).

## Швидкий контекст

- **Active code**: `src/`
- **Old reference (не видаляти поки мігруємо)**: `storage/src/`
- **Old configs (reference, не підключені)**: `storage/config/`
- **Namespace**: `Concept\Core\`
- **PHP**: 8.4, PHPStan level 9

Skeleton підключає цей проєкт через symlink:

```
/var/www/concept-skeleton-dev-2/vendor/php-concept/core-2 → /var/www/concept-core-2/
```

## Що входить у тонке ядро

| Компонент | Файл |
|-----------|------|
| Application | `src/App.php` |
| HTTP provider | `src/Providers/Http/HttpServiceProvider.php` |
| Route strategy | `src/Http/Routing/RouteStrategy.php` |
| Resolver contract | `src/Http/Contracts/ArgumentResolverInterface.php` |
| Interceptor contract | `src/Http/Contracts/RouteInterceptorInterface.php` |

## Що НЕ входить у ядро (→ extensions у skeleton)

- FormRequest / Validation
- Caster / Valinor
- Session, View, Database, Telemetry, Console, …
- **Config** — extension на фінальному етапі (зараз **без ConfigInterface**)

Див. `storage/src/` та `storage/config/` як reference для міграції.

## Конфігурація — без ConfigInterface

Під час міграції **ядро не використовує ConfigInterface**. Усі налаштування передаються skeleton-ом явно (масиви, параметри конструкторів providers).

`storage/config/` — PHP-файли старого додатку (`app.php`, `db.php`, `routes.php`, …). Reference only; не autoload, не bootstrap.

Config extension (пізніше) читатиме конфіги і прокидатиме значення в glue — ядро лишається без Config.

## Enriched request — без container refresh

`RouteStrategy::prepareRequest()` додає route vars у PSR-7 attributes і повертає enriched request для resolvers/handler.

**Не робить** `container->add(ServerRequestInterface, …)`. Container request (globals) — тільки entry point у `App::handle()`.

Extensions отримують request явно (resolver args, method params). `RequestProxy` — не в core; у Http extension лише за потреби.

## Argument resolvers — core (implemented)

`src/Http/Routing/Resolvers/`:

1. **ServerRequestArgumentResolver** — type hint `ServerRequestInterface`
2. **RouteParameterArgumentResolver** — param name ∈ route `$vars`, raw value

Registered by default in `HttpServiceProvider::resolveResolvers()`.
Extension resolvers from constructor are prepended (higher priority).

## Залежності (composer.json)

- `league/container`, `league/route`
- `laminas/laminas-diactoros`, `laminas/laminas-httphandlerrunner`

Без Valinor, без validator, без twig/plates.

## Перевірка

```bash
cd /var/www/concept-core-2
vendor/bin/phpstan
```

---

*Детальний roadmap і поточний стан — у skeleton AGENTS.md.*
