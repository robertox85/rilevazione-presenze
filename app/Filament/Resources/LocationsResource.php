<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\RelationManagers\HolidaysRelationManager;
use App\Filament\Resources\LocationResource\RelationManagers\UsersRelationManager;
use App\Filament\Resources\LocationsResource\Pages;
use App\Filament\Resources\LocationsResource\RelationManagers;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Components\HasManyRepeater;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocationsResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {

        $general_info = [
            Forms\Components\Grid::make('2')
                ->schema([
                    Forms\Components\Toggle::make('active')
                        ->required()->columnSpanFull()->default(true),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('address')
                        ->label('Full Address')
                        ->maxLength(255),

                    Forms\Components\Select::make('timezone')
                        ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->searchable(),

                    Forms\Components\Section::make('Address Details')
                        ->columnSpanFull()
                        ->collapsed()
                        ->collapsible()
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('street')
                                        ->disabled()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('number')
                                        ->disabled()
                                        ->maxLength(10),
                                    Forms\Components\TextInput::make('city')
                                        ->disabled()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('state')
                                        ->disabled()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('postal_code')
                                        ->disabled()
                                        ->maxLength(10),
                                    Forms\Components\TextInput::make('country_code')
                                        ->disabled()
                                        ->maxLength(2),
                                    Forms\Components\TextInput::make('latitude')
                                        ->disabled()
                                        ->numeric(),
                                    Forms\Components\TextInput::make('longitude')
                                        ->disabled()
                                        ->numeric(),
                                ]),
                        ])
                    ,


                ])
        ];

        $working_time = [
            Forms\Components\Grid::make('2')
                ->schema([
                    Forms\Components\TimePicker::make('working_start_time')
                        ->format('H:i')
                        ->seconds(false)
                        ->default('09:00'),
                    Forms\Components\TimePicker::make('working_end_time')
                        ->format('H:i')
                        ->seconds(false)
                        ->default('18:00'),
                ])
        ];

        $holiday_exclusion = [
            Forms\Components\Toggle::make('exclude_holidays')
                ->label('Assume holidays as non-working days')
                ->helperText('If active, holidays will not be considered as working days')
                ->default(true)
                ->required(),
        ];

        $working_days = [
            Forms\Components\Grid::make('2')
                ->schema([
                    Forms\Components\CheckboxList::make('working_days')
                        ->options([
                            1 => 'Monday',
                            2 => 'Tuesday',
                            3 => 'Wednesday',
                            4 => 'Thursday',
                            5 => 'Friday',
                            6 => 'Saturday',
                            7 => 'Sunday',
                        ])
                        ->default([1, 2, 3, 4, 5])
                        ->required(),
                ])
        ];

        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('General Information')
                            ->schema($general_info),
                        Tabs\Tab::make('Working Days')
                            ->schema($working_days),
                        Tabs\Tab::make('Working Time')
                            ->schema($working_time),
                        Tabs\Tab::make('Holiday Exclusion')
                            ->schema($holiday_exclusion),

                    ]),


            ]);
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('street')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\TextColumn::make('postal_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('timezone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('working_start_time'),
                Tables\Columns\TextColumn::make('working_end_time'),
                Tables\Columns\TextColumn::make('working_days')
                    ->searchable(),
                Tables\Columns\IconColumn::make('exclude_holidays')
                    ->boolean(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            UsersRelationManager::class,
            HolidaysRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocations::route('/create'),
            'edit' => Pages\EditLocations::route('/{record}/edit'),
        ];
    }
}
