<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use BezhanSalleh\FilamentShield\FilamentShield;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {

        $rows = [

            TextInput::make('email')
                ->email()
                ->required()
                ->label(trans('filament-users::user.resource.email')),

            TextInput::make('name')
                ->required()
                ->label(trans('filament-users::user.resource.name')),

            TextInput::make('surname'),
            TextInput::make('tax_code')
                ->maxLength(16),

            TextInput::make('password')
                ->label(trans('filament-users::user.resource.password'))
                ->password()
                ->maxLength(255)
                ->dehydrateStateUsing(static function ($state, $record) use ($form) {
                    return !empty($state)
                        ? Hash::make($state)
                        : $record->password;
                }),

            Forms\Components\Select::make('contract_type')
                ->options([
                    'FULL_TIME' => 'Full Time',
                    'EXTERNAL' => 'External',
                ])->default('FULL_TIME'),

            Forms\Components\Select
                ::make('location_id')
                ->relationship('location', 'name')
                ->required()
                ->label('Location')
                ->default(1),

        ];




        if (config('filament-users.shield') && class_exists(FilamentShield::class)) {
            $rows[] = Forms\Components\Select::make('roles')
                ->relationship('roles', 'name')
                ->hidden()
                ->default([
                    '2'
                ])
                ->selectablePlaceholder(false)
                ->label(trans('filament-users::user.resource.roles'));
        }

        $form->schema([
            Forms\Components\Section::make('General Info')
                ->columns(2)
                ->schema($rows)
            ,


            Forms\Components\Section::make('Privacy')
                ->columns(2)
                ->hidden()
                ->schema([
                    Forms\Components\DateTimePicker::make('privacy_accepted_at')
                        ->hidden()
                        ->label('Privacy Accepted At')
                        ->columnSpanFull()
                        ->seconds(false)
                        ->default(now()),


                    Forms\Components\Toggle::make('geolocation_consent')
                        ->hidden()
                        ->label('Geolocation Accepted')
                        ->columnSpanFull()
                        ->default(true),
                ])
        ]);

        return $form;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('surname'),
                Tables\Columns\TextColumn::make('email'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([

            ]);
    }
}
