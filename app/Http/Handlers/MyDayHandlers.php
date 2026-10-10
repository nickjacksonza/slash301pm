<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Dates;
use App\Domain\MyDay;
use App\Domain\MyDayMode;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Signals\SignalInput;
use App\Domain\Types\MyDayJob;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\View\VM\MyDayVM;

/**
 * GET /today, GET /today/body, GET /today/sections/{section}, POST /today/seen.
 * The sections depend on Policy::myDayMode(). The brand row filters every
 * section: today.brand (signal; ?brand= on the page) is applied only when it
 * is one of the brands of the user's own My day jobs.
 */
final class MyDayHandlers
{
    public static function page(Request $r, Deps $d): Response
    {
        $vm = self::vm(self::user($r), $d, $r->query('brand'));
        // Social publishing
        $social = SocialHandlers::today($d, self::user($r));
        return Shell::page($r, $d, 'My day', 'today', page_today($vm, $social));
    }

    /** One section, patched by id; the page asks for each every 60 seconds. */
    public static function section(Request $r, Deps $d): Response
    {
        $key = $r->pathValue('section');
        $u = self::user($r);
        if (!in_array($key, MyDay::sectionKeys(Policy::myDayMode($u)), true)) {
            return Response::notFound();
        }
        return Response::events(PatchElements::html(partial_today_section($key, self::vm($u, $d, self::brandSignal($r)))));
    }

    /** GET /today/body: a brand button (or "All") was pressed; patches #today-body (brand row and every section). */
    public static function body(Request $r, Deps $d): Response
    {
        return Response::events(PatchElements::html(partial_today_body(self::vm(self::user($r), $d, self::brandSignal($r)))));
    }

    private static function brandSignal(Request $r): string
    {
        return trim(SignalInput::str(SignalInput::obj($r->signals(), 'today'), 'brand'));
    }

    /** "Mark all seen": changes before now stop showing. Answers with the emptied section. */
    public static function markSeen(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $d->myDay->markSeen($u->id, $d->clock->now());
        return Response::events(PatchElements::html(partial_today_section(MyDay::CHANGED, self::vm($u, $d, self::brandSignal($r)))));
    }

    private static function vm(User $u, Deps $d, string $wantBrand = ''): MyDayVM
    {
        $now = $d->clock->now();
        $since = MyDay::since($d->myDay->seenAt($u->id), $now);
        $mode = Policy::myDayMode($u);
        $first = trim(explode(' ', $u->name)[0]);
        $greeting = 'Good ' . Dates::partOfDay($now) . ($first !== '' ? ', ' . $first : '');
        if ($mode !== MyDayMode::Owner) {
            $jobs = $d->myDay->assignedOpen($u->id);
            $brands = MyDay::brands($jobs, $mode);
            $brand = MyDay::selectedBrand($wantBrand, $brands);
            $result = MyDay::buildAssigned($u->id, $mode, MyDay::jobsOfBrand($jobs, $brand),
                MyDay::changesOfBrand($d->myDay->changesSince($u->id, $since, 100, $mode), $brand), $since, $now);
            return new MyDayVM($greeting, Dates::longDate($now), $result, false, $brands, $brand);
        }
        $unowned = [];
        if (in_array($u->role, [Role::AM, Role::PM, Role::Producer], true)) {
            foreach ($d->jobs->listWithoutAm(50) as $it) {
                $unowned[] = MyDayJob::fromListItem($it);
            }
        }
        $jobs = $d->myDay->ownedOpen($u->id);
        $brands = MyDay::brands($jobs, $mode);
        $brand = MyDay::selectedBrand($wantBrand, $brands);
        $result = MyDay::build($u->id, $u->role, MyDay::jobsOfBrand($jobs, $brand), MyDay::jobsOfBrand($unowned, $brand),
            MyDay::changesOfBrand($d->myDay->changesSince($u->id, $since), $brand), $since, $now);
        return new MyDayVM($greeting, Dates::longDate($now), $result, Policy::canCreateBrief($u)->allowed, $brands, $brand);
    }

    private static function user(Request $r): User
    {
        $u = $r->user();
        if ($u === null) {
            throw new \LogicException('route needs a signed-in user');
        }
        return $u;
    }
}
