---
name: go-portable-php
description: Rules and patterns that keep Slash 301 PM's PHP code mechanically portable to Go. Load before writing or reviewing any PHP under app/ (Domain, Store, Http, View), tests/ or tools/.
---

# Go-portable PHP

The app runs on PHP 8.3+ today and may be ported to Go (net/http ServeMux, database/sql with modernc sqlite, templ with DatastarUI, datastar-go). Write PHP so each file converts line by line.

## The rules (checklist)
1. `declare(strict_types=1);` at the top of every file. PHP 8.3 syntax only: no property hooks, no asymmetric visibility.
2. No magic: no `__get`/`__set`/`__call`, no dynamic properties, no `extract()`, no variable variables, no DI container, no facades, no globals outside `app/bootstrap.php`.
3. Namespace = Go package: `App\Domain` → `domain`, `App\Store` → `store`, `App\Http` → `web`, `App\View\ui` → `datastarui`.
4. Data crosses boundaries as `final` classes with constructor-promoted `public readonly` properties (Go structs). Never pass loose arrays between layers. Each DTO has `static fromRow(array $r): self` and, if it comes from the browser, `static fromSignals(array $s): self`.
5. Fixed sets are string-backed enums (`enum Stage: string`), which become Go `type Stage string` constants.
6. `App\Domain` is pure: no database, session, superglobals, clock or randomness. Pass `$now` (a `DateTimeImmutable`) and IDs in. Validation returns a `ValidationErrors` value; it does not throw. Exceptions are for programmer or infrastructure errors only.
7. SQL lives only in `App\Store`. Prepared statements only. Writes go through `Db::txImmediate(callable)` (BEGIN IMMEDIATE). Generate IDs in PHP before INSERT; no `RETURNING` (SQLite 3.34 on the server).
8. Handlers: `static function name(Request $r, Deps $d): Response`. `Deps` is an explicit readonly bag (stores, clock, config). A `Response` is a full page or a list of events (`PatchElements`, `PatchSignals`, `Toast`, `Redirect`). Only `Response::send()` touches the Datastar SDK or headers.
9. Routes in `app/routes.php` use Go 1.22 ServeMux patterns: `['PATCH /jobs/{id}/fields/{field}', [JobHandlers::class, 'updateField']]`. One route per action.
10. Templates are functions of one view model: `function page_jobs_grid(JobsGridVM $vm): string`. They never read the database, session, request or config. All escaping through `e()`, `attr()`, `js()`.
11. Authorization is a pure `Policy` function per action returning `Decision{allowed, reason}`. The actor always comes from the session; every `*_by` column is set from it, never from input.
12. No PHP-only conveniences that Go lacks without thought: avoid `array_walk` by reference, references in `foreach`, `list()` with keys on mixed arrays, implicit int/string juggling. Use explicit loops and typed returns.

## Patterns

DTO:
```php
<?php
declare(strict_types=1);
namespace App\Domain\Types;

final class Job
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly Stage $stage,
        public readonly ?string $dueDate,   // 'Y-m-d' or null
        public readonly int $rowVersion,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['job_number'], (string) $r['title'],
            Stage::from((string) $r['stage']), $r['delivery_date'] !== null ? (string) $r['delivery_date'] : null,
            (int) $r['row_version'],
        );
    }
}
```
Go: `type Job struct { ID string \`db:"id"\`; JobNumber string \`db:"job_number"\` ... }`.

Handler:
```php
public static function updateField(Request $r, Deps $d): Response
{
    $user = $r->user();                                   // from session, never input
    $job  = $d->jobs->get($r->pathValue('id'));
    if ($job === null) return Response::notFound();
    $edit = EditCellSignals::fromSignals($r->signals());
    $decision = Policy::canUpdateField($user, $job, $r->pathValue('field'));
    if (!$decision->allowed) return Response::events(Toast::error($decision->reason));
    $result = $d->jobs->updateField($job->id, $r->pathValue('field'), $edit->value, $edit->rowVersion, $user->id, $d->clock->now());
    $row = page_job_row(JobRowVM::from($result->job, $user));
    return Response::events(PatchElements::html($row), $result->conflict ? Toast::warn('Changed by someone else; showing the latest.') : Toast::ok('Saved'));
}
```
Go: `func (h *JobHandlers) UpdateField(w http.ResponseWriter, r *http.Request)` with `r.PathValue("id")` and `datastar.NewSSE(w, r)`.

Template:
```php
function page_job_row(JobRowVM $vm): string
{
    ob_start(); ?>
<tr id="job-row-<?= attr($vm->id) ?>" class="border-b border-border hover:bg-muted/50">
  <td class="px-3 py-2 font-mono text-xs"><?= e($vm->jobNumber) ?></td>
  <td class="px-3 py-2" data-on:click="@get(<?= js($vm->editUrls['title']) ?>)"><?= e($vm->title) ?></td>
</tr>
<?php return (string) ob_get_clean();
}
```

## Review questions
- Could this file be translated to Go without redesign? If not, which rule does it break?
- Does any Domain function read the clock, session or database?
- Does any `*_by` value or status come from input?
- Is every dynamic value escaped by the right helper for its context (text, attribute, Datastar expression)?
