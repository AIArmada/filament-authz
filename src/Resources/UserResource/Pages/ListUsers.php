<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\UserResource\Pages;

use AIArmada\FilamentAuthz\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        $table->recordUrl(fn ($record): ?string => $this->getResourceUrl('view', ['record' => $record]));

        return $table;
    }
}
