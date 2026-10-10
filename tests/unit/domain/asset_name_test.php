<?php
declare(strict_types=1);

use App\Domain\AssetName;

require_once dirname(__DIR__, 2) . '/support/app.php';

/**
 * Expected values were produced by running legacy/src/utils.js generateAssetName
 * in Node 22 with Date fixed at 2026-10-09T23:30:00Z (the PHP clock below is the
 * same instant in SAST, 2026-10-10 01:30, so the UTC date rule is exercised).
 */
return [
    'asset name: exact port of legacy generateAssetName' => function (): void {
        $now = new DateTimeImmutable('2026-10-10 01:30:00', new DateTimeZone('Africa/Johannesburg'));
        $cases = [
            [['MERC-004', 'The Meridian Collection', 'Grand Opening London', 'SocialPostStatic', 1, 1, null], 'MERC-004-TheMeridia-GrandOpeningLon-SocialPostStatic-v1-20261009'],
            [['HARB-010', 'Harbour & Helm Hotels', 'summer SAILING season', 'StoryReel2', 2, 1, '1080x1920'], 'HARB-010-HarbourHel-SummerSailingSe-StoryReel2-v1-20261009_1080x1920'],
            [['X-001', '', '', '', 3, 2, null], 'X-001-Client-Campaign-Asset3-v2-20261009'],
            [['X-002', null, null, null, 1, 1, null], 'X-002-Client-Campaign-Asset1-v1-20261009'],
            [['COAS-001', 'Coastal & Co. Resorts', '  double  space  ', 'Ad', 1, 1, null], 'COAS-001-CoastalCoR-DoubleSpace-Ad-v1-20261009'],
            [['LENA-001', 'Müller & Söhne GmbH', 'größe ıstanbul été', 'Banner', 1, 3, null], 'LENA-001-MllerShneG-GreIstanbult-Banner-v3-20261009'],
            [['Z-999', '<script>"\'&', '<b>x</b> {{y}} 12 ab-cd', 'T', 1, 1, ''], 'Z-999-script-bxby12Abcd-T-v1-20261009'],
            [['VELA-001', 'Vela Luxury Resorts International Group', 'Island Escape Collection Twenty Twenty Six', 'PrintAd', 1, 1, null], 'VELA-001-VelaLuxury-IslandEscapeCol-PrintAd-v1-20261009'],
        ];
        foreach ($cases as [$a, $want]) {
            t_eq($want, AssetName::generate($a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $now), $want);
        }
    },
    'asset name: helpers' => function (): void {
        t_eq('SocialPostStatic', AssetName::typeToken('Social Post (Static)'));
        t_eq('StoryReel', AssetName::typeToken('Story/Reel'));
        t_eq('1080x1350', AssetName::sizeToken('1080 x 1350 px, IG'));
        t_eq('1080x1920', AssetName::sizeToken('1080×1920'));
        t_eq('', AssetName::sizeToken('A4 portrait'));
        t_eq(1, preg_match('/^[A-Z]{2,4}-\d{3}-[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+-v\d+-\d{8}(_\d+x\d+)?$/',
            AssetName::generate('MERC-004', 'The Meridian Collection', 'Grand Opening London', 'SocialPostStatic2', 2, 1, '1080x1350', new DateTimeImmutable('2026-10-09 12:00'))), 'legacy validateAssetName accepts it');
    },
];
