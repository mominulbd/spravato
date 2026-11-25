# Domain modules

One directory per module, loaded by `aminulbd/laravel-packages` and live only when its id is listed in `config/packages.php`. Conventions and the planned catalogue: [docs/ARCHITECTURE.md §3](../docs/ARCHITECTURE.md).

```text
packages/<module>/
├── index.php            id uptms.<snake> · autoload Uptms\<Pascal>\ → src/ · provider · require
├── migrations/
├── routes/{api,api_public,api_admin}.php
├── seeders/
├── src/                 <Pascal>ServiceProvider extends App\Support\Packages\ModuleServiceProvider
└── tests/{Feature,Unit}
```
