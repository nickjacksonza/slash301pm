<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Asset;
use App\Domain\Types\AssetNaming;
use App\Domain\Types\AssetPlanResult;
use App\Domain\Types\AssetWarning;
use App\Domain\Types\BriefLine;
use App\Domain\Types\PlannedAsset;

/**
 * Deliverable lines -> legacy assets rows, on send and on every update (ADR 0003):
 *  - fewer live (non-cancelled) assets than qty: add the difference, named by AssetName;
 *  - more than qty: cancel the newest unstarted ones; started ones are kept and warned about;
 *  - a removed line: cancel its unstarted assets, warn about started ones.
 * Assets without a line (legacy, no template) are never touched. Names of
 * existing assets never change.
 */
final class AssetPlan
{
    /**
     * @param list<BriefLine> $lines current working copy
     * @param list<Asset> $assets every asset of the job
     * @param int $sortFrom lowest sort order for new assets (when $assets is not every asset of the job)
     */
    public static function plan(array $lines, array $assets, AssetNaming $ctx, int $sortFrom = 0): AssetPlanResult
    {
        $create = [];
        $cancel = [];
        $warnings = [];
        $sort = $sortFrom;
        foreach ($assets as $a) {
            $sort = max($sort, $a->sortOrder + 1);
        }
        $lineIds = [];
        foreach ($lines as $line) {
            $lineIds[$line->id] = true;
            $linked = self::linked($assets, $line->id);
            $live = [];
            foreach ($linked as $a) {
                if (!AssetStatus::isCancelled($a->status)) {
                    $live[] = $a;
                }
            }
            $qty = max(0, $line->qty);
            if (count($live) < $qty) {
                $template = AssetTemplates::find($line->templateId);
                $type = $template !== null ? $template->type : 'document';
                $base = AssetName::typeToken($line->label !== '' ? $line->label : ($template !== null ? $template->name : ''));
                $numbered = $qty > 1 || $linked !== [];
                $seq = count($linked);
                for ($i = count($live); $i < $qty; $i++) {
                    $seq++;
                    $name = AssetName::generate(
                        $ctx->jobNumber, $ctx->brandName, $ctx->campaignName,
                        $base === '' ? '' : ($numbered ? $base . $seq : $base),
                        $seq, 1, AssetName::sizeToken($line->sizeFormat), $ctx->now,
                    );
                    $create[] = new PlannedAsset($line->id, $name, $type, $line->templateId, $line->dueDate ?? $ctx->briefDueDate, $sort,
                        $template !== null ? $template->defaultRole : null);
                    $sort++;
                }
            } elseif (count($live) > $qty) {
                $excess = count($live) - $qty;
                [$cancelled, $kept] = self::cancelUnstarted($live, $excess);
                foreach ($cancelled as $id) {
                    $cancel[] = $id;
                }
                if ($kept > 0) {
                    $warnings[] = new AssetWarning($line->id, $line->label, $kept,
                        $line->label . ': ' . $kept . ' started ' . ($kept === 1 ? 'asset was' : 'assets were') . ' kept although the quantity is now ' . $qty . '.');
                }
            }
        }
        // removed lines
        $orphanLines = [];
        foreach ($assets as $a) {
            if ($a->briefAssetId !== null && !isset($lineIds[$a->briefAssetId]) && !AssetStatus::isCancelled($a->status)) {
                $orphanLines[$a->briefAssetId][] = $a;
            }
        }
        foreach ($orphanLines as $lineId => $live) {
            [$cancelled, $kept] = self::cancelUnstarted($live, count($live));
            foreach ($cancelled as $id) {
                $cancel[] = $id;
            }
            if ($kept > 0) {
                $warnings[] = new AssetWarning((string) $lineId, $live[0]->name, $kept,
                    'A removed deliverable still has ' . $kept . ' started ' . ($kept === 1 ? 'asset' : 'assets') . ' (' . $live[0]->name . '); ' . ($kept === 1 ? 'it was' : 'they were') . ' kept.');
            }
        }
        return new AssetPlanResult($create, $cancel, $warnings);
    }

    /** @param list<Asset> $assets @return list<Asset> */
    private static function linked(array $assets, string $lineId): array
    {
        $out = [];
        foreach ($assets as $a) {
            if ($a->briefAssetId === $lineId) {
                $out[] = $a;
            }
        }
        return $out;
    }

    /**
     * Cancel up to $n unstarted assets, newest (highest sort order) first.
     * @param list<Asset> $live
     * @return array{0:list<string>,1:int} cancelled ids, started assets that had to be kept
     */
    private static function cancelUnstarted(array $live, int $n): array
    {
        $sorted = $live;
        usort($sorted, static fn (Asset $a, Asset $b): int => [$b->sortOrder, $b->id] <=> [$a->sortOrder, $a->id]);
        $ids = [];
        foreach ($sorted as $a) {
            if (count($ids) >= $n) {
                break;
            }
            if (!AssetStatus::isStarted($a->status)) {
                $ids[] = $a->id;
            }
        }
        return [$ids, $n - count($ids)];
    }
}
