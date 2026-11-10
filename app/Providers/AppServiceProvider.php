<?php

namespace App\Providers;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat('d.m.Y')
            ->defaultDateTimeDisplayFormat('d.m.Y H:i'));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat('d.m.Y')
            ->defaultDateTimeDisplayFormat('d.m.Y H:i'));
    }
}
