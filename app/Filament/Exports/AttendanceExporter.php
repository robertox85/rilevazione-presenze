<?php

namespace App\Filament\Exports;

use App\Models\Attendance;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AttendanceExporter extends Exporter
{
    protected static ?string $model = Attendance::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('user_id'),

            ExportColumn::make('name')
                ->default(function (Attendance $attendance) {
                    return $attendance->user->name;
                }),
            ExportColumn::make('surname')
                ->default(function (Attendance $attendance) {
                    return $attendance->user->surname;
                }),

            ExportColumn::make('device_id'),
            ExportColumn::make('date'),
            ExportColumn::make('check_in'),
            ExportColumn::make('check_out'),
            ExportColumn::make('check_in_latitude'),
            ExportColumn::make('check_in_longitude'),
            ExportColumn::make('check_out_latitude'),
            ExportColumn::make('check_out_longitude'),
            ExportColumn::make('notes'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your attendance export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
