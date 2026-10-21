<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiClient\Pages;

use App\MoonShine\Resources\ApiClient\ApiClientResource;
use Illuminate\Support\Str;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<ApiClientResource>
 */
final class ApiClientFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make('API Mijoz Ma\'lumotlari', [
                ID::make(),
                Text::make('Mijoz yoki Tizim Nomi', 'name')
                    ->required()
                    ->hint('Masalan: Ipak Yo\'li Banki, Markaziy Turniket #1, ERP HR'),
                Textarea::make('Tavsif / Izoh', 'description')
                    ->nullable(),
                Text::make('Client ID', 'client_id')
                    ->default(static fn() => 'clnt_' . Str::random(20))
                    ->required()
                    ->copy()
                    ->hint('Basic Auth uchun username sifatida ishlatiladi'),
                Text::make('Client Secret (Maxfiy Kalit)', 'client_secret')
                    ->default(static fn() => 'sec_' . Str::random(32))
                    ->required()
                    ->copy()
                    ->hint('Basic Auth uchun password yoki JSON da client_secret sifatida yuboriladi'),
                Switcher::make('Faol holatda (Active)', 'is_active')
                    ->default(true),
                Number::make('Daqiqasiga so\'rovlar soni (Rate Limit)', 'rate_limit_per_minute')
                    ->default(120)
                    ->required(),
                Text::make('Ruxsat etilgan IP lar (Allowed IPs)', 'allowed_ips')
                    ->nullable()
                    ->hint('Vergul bilan ajrating. Bo\'sh qoldirilsa, barcha IP manzillardan ulanishga ruxsat beriladi'),
                Date::make('Litsenziya / Amal qilish muddati', 'expires_at')
                    ->nullable()
                    ->hint('Bo\'sh qoldirilsa, muddati cheksiz bo\'ladi'),
            ]),
        ];
    }
}
