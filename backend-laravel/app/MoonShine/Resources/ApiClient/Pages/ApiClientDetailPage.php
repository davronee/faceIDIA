<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiClient\Pages;

use App\MoonShine\Resources\ApiClient\ApiClientResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<ApiClientResource>
 */
final class ApiClientDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Mijoz Nomi', 'name'),
            Textarea::make('Tavsif', 'description'),
            Text::make('Client ID', 'client_id')->copy(),
            Text::make('Client Secret', 'client_secret')->copy(),
            Switcher::make('Faol', 'is_active'),
            Number::make('Rate Limit (daqiqasiga)', 'rate_limit_per_minute'),
            Text::make('Ruxsat etilgan IP lar', 'allowed_ips'),
            Date::make('Oxirgi faollik', 'last_used_at')->format('d.m.Y H:i'),
            Date::make('Amal qilish muddati', 'expires_at')->format('d.m.Y'),
            Date::make('Yaratilgan sana', 'created_at')->format('d.m.Y H:i'),
        ];
    }
}
