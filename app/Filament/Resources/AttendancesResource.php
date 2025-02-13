<?php

namespace App\Filament\Resources;

use App\Filament\Exports\AttendanceExporter;

use App\Filament\Resources\AttendancesResource\Pages;
use App\Filament\Resources\AttendancesResource\RelationManagers;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttendancesResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $label = 'Attendance';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('device_id')
                    ->required()
                    ->numeric(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('check_in')
                    ->required(),
                Forms\Components\TextInput::make('check_out'),
                Forms\Components\TextInput::make('check_in_latitude')
                    ->numeric(),
                Forms\Components\TextInput::make('check_in_longitude')
                    ->numeric(),
                Forms\Components\TextInput::make('check_out_latitude')
                    ->numeric(),
                Forms\Components\TextInput::make('check_out_longitude')
                    ->numeric(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // user.name
                // Location.name
                // Date

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),


                // Location is in the User model
                Tables\Columns\TextColumn::make('user.location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                // Two columns for the date, date and day
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->searchable()
                    ->sortable(),


                // Only the time of the check in
                Tables\Columns\TextColumn::make('check_in')
                    ->label('Check In')
                    ->formatStateUsing(function ($state) {
                        return date('H:i', strtotime($state));
                    })
                    ->searchable()
                    ->sortable()
                ,

                // check out formatted as date time (d/m/Y H:i)
                Tables\Columns\TextColumn::make('check_out')
                    ->label('Check Out')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return date('H:i', strtotime($state));
                    })
                    ->sortable(),

                // device name
                Tables\Columns\TextColumn::make('device.device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(AttendanceExporter::class)
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendances::route('/create'),
            'edit' => Pages\EditAttendances::route('/{record}/edit'),
        ];
    }
}
