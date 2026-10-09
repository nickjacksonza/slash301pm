<?php
declare(strict_types=1);

require_once __DIR__ . '/_fx.php';

use App\View\ui\PartProps;
use App\View\ui\SheetCloseProps;
use App\View\ui\SheetContentProps;
use App\View\ui\SheetProps;
use App\View\ui\SheetTriggerProps;

$p = static fn (): PartProps => new PartProps();

return ui_fx_cases('sheet', [
    'sheet' => static fn (): string => ui_sheet(
        new SheetProps(id: 'filters', side: 'right', modal: true),
        ui_sheet_content(
            new SheetContentProps(sheetId: 'filters'),
            ui_sheet_header($p(), ui_sheet_title($p(), 'Filters') . ui_sheet_description($p(), 'Narrow the list.')) . 'Body' . ui_sheet_footer($p(), 'Foot')
        )
    ),
    'sheet.left' => static fn (): string => ui_sheet(new SheetProps(id: 'nav', side: 'left'), 'Nav'),
    'sheet.top.open' => static fn (): string => ui_sheet(new SheetProps(id: 'tp', side: 'top', defaultOpen: true, modal: true), 'Top'),
    'sheet.bottom' => static fn (): string => ui_sheet(new SheetProps(id: 'bt', side: 'bottom', attrs: ['data-x' => 'y']), 'B'),
    'sheet.trigger' => static fn (): string => ui_sheet_trigger(new SheetTriggerProps(sheetId: 'filters', class: 'underline'), 'Open'),
    'sheet.trigger.aschild' => static fn (): string => ui_sheet_trigger(new SheetTriggerProps(sheetId: 'filters', asChild: true), 'Open'),
    'sheet.close' => static fn (): string => ui_sheet_close(new SheetCloseProps(sheetId: 'filters', returnValue: 'ok', class: 'x'), 'Close'),
    'sheet.close.aschild' => static fn (): string => ui_sheet_close(new SheetCloseProps(sheetId: 'filters', asChild: true), 'Close'),
]) + [
    'sheet content container id is configurable' => static function (): void {
        $h = ui_sheet(new SheetProps(id: 'sheet', modal: true), '<p>x</p>');
        t_contains('id="sheet"', $h);
        t_contains('data-signals="{&quot;sheet&quot;:{&quot;open&quot;:false,&quot;modal&quot;:true,&quot;returnValue&quot;:null}}"', $h);
    },
];
