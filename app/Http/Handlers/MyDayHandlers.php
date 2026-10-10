<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\Dates;
use App\Domain\MyDay;
use App\Domain\MyDayMode;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Types\MyDayJob;
use App\Domain\Types\User;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\View\VM\MyDayVM;

/** GET /today, GET /today/sections/{section}, POST /today/seen. The sections depend on Policy::myDayMode(). */
final class MyDayHandlers
{
    public static function page(Request $r, Deps $d): Response
    {
        $vm = self::vm(self::user($r), $d);
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
        return Response::events(PatchElements::html(partial_today_section($key, self::vm($u, $d))));
    }

    /** "Mark all seen": changes before now stop showing. Answers with the emptied section. */
    public static function markSeen(Request $r, Deps $d): Response
    {
        $u = self::user($r);
        $d->myDay->markSeen($u->id, $d->clock->now());
        return Response::events(PatchElements::html(partial_today_section(MyDay::CHANGED, self::vm($u, $d))));
    }

    private static function vm(User $u, Deps $d): MyDayVM
    {
        $now = $d->clock->now();
        $since = MyDay::since($d->myDay->seenAt($u->id), $now);
        $mode = Policy::myDayMode($u);
        $first = trim(explode(' ', $u->name)[0]);
        $greeting = 'Good ' . Dates::partOfDay($now) . ($first !== '' ? ', ' . $first : '');
        if ($mode !== MyDayMode::Owner) {
            $result = MyDay::buildAssigned($u->id, $mode, $d->myDay->assignedOpen($u->id), $d->myDay->changesSince($u->id, $since, 100, $mode), $since, $now);
            return new MyDayVM($greeting, Dates::longDate($now), $result, false);
        }
        $unowned = [];
        if (in_array($u->role, [Role::AM, Role::PM, Role::Producer], true)) {
            foreach ($d->jobs->listWithoutAm(50) as $it) {
                $unowned[] = MyDayJob::fromListItem($it);
            }
        }
        $result = MyDay::build($u->id, $u->role, $d->myDay->ownedOpen($u->id), $unowned, $d->myDay->changesSince($u->id, $since), $since, $now);
        return new MyDayVM($greeting, Dates::longDate($now), $result, Policy::canCreateBrief($u)->allowed);
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
