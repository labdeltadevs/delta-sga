---
paths:
  - 'resources/views/pages/**'
---

# Pages

## SFC computed props need `$this->` in Blade
In Livewire SFC page components (`resources/views/pages/**/⚡*.blade.php`), `#[Computed]` properties MUST be referenced in the Blade template as `$this->computedName`, never as a bare `$computedName` variable — bare references throw "Undefined variable" 500s (e.g. dashboard's `$pendienteConteo`).

## Avoid MySQL-only SQL functions in SFC pages
The app runs on PostgreSQL (dev/prod) and SQLite (tests). Never use MySQL-only raw SQL like selectRaw('YEAR(fecha)') / IFNULL / DATE_FORMAT — Postgres raises SQLSTATE[42883]. Use portable Eloquent (whereYear/whereMonth/whereDay, get + collection map) instead. Prefer building year lists in PHP via collect() so it works on both drivers.

## Do not call blade() in SFC attribute bindings
There is no global blade() helper function. Do not use :class="blade('px-3', ...)" in SFC component attributes — the : prefix compiles the value as a PHP expression, so undefined blade() causes a 500. Use a plain PHP ternary returning the class string instead, e.g. :class="$filtro === 'x' ? 'active-classes' : 'inactive-classes'".
