<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Person\Pages;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Components\ActionButton;
use MoonShine\Support\ListOf;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\UI\Components\Thumbnails;
use App\Models\Person;

/**
 * @extends IndexPage<PersonResource>
 */
class PersonIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make('Surat', 'photo_url')
                ->changePreview(function ($value, $field) {
                    $item = $field->getData()?->getOriginal();
                    if (!$item || empty($item->getRawOriginal('photo_url'))) {
                        return '—';
                    }

                    return Thumbnails::make(
                        route('admin.biometrics.photo', $item->id)
                    );
                }),
            Text::make('F.I.Sh', 'full_name')->sortable(),
            Text::make('JSHSHIR (PINFL)', 'pinfl')->sortable(),
            Text::make('Telefon / Hujjat', 'phone_number'),
            Text::make('Bo\'lim / Maqsad', 'department'),
            Text::make('Holati', 'status')
                ->badge(fn($status) => match($status) {
                    'approved' => 'green',
                    'pending' => 'yellow',
                    default => 'red',
                }),
            Date::make('Kelgan vaqti', 'created_at')
                ->format('d.m.Y H:i')
                ->sortable(),
        ];
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function topLeftButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, []);
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make('Barchasi', fn($q) => $q)->default(),
            QueryTag::make('Tasdiqlanganlar (Face ID)', fn($q) => $q->where('status', 'approved')),
        ];
    }
}
