<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiClient\Pages;

use App\MoonShine\Resources\ApiClient\ApiClientResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ApiClientResource>
 */
final class ApiClientIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Mijoz Nomi', 'name')->sortable(),
            Text::make('Client ID', 'client_id')->copy(),
            Switcher::make('Faol', 'is_active')->updateOnPreview(),
            Number::make('Rate Limit (daq)', 'rate_limit_per_minute')->sortable(),
            Date::make('Oxirgi faollik', 'last_used_at')->format('d.m.Y H:i')->sortable(),
            Date::make('Amal qilish', 'expires_at')->format('d.m.Y')->sortable(),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Mijoz Nomi', 'name'),
            Text::make('Client ID', 'client_id'),
            Switcher::make('Faqat faollar', 'is_active'),
        ];
    }
}
