<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Person\Pages;

use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\Contracts\UI\FieldContract;
use App\MoonShine\Resources\Person\PersonResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Components\Thumbnails;
use Throwable;

/**
 * @extends DetailPage<PersonResource>
 */
class PersonDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Image::make('Jonli Yuz Surati (Kamera)', 'photo_url')
                ->changePreview(function ($value, $field) {
                    $item = $field->getData()?->getOriginal();
                    if (!$item || empty($item->getRawOriginal('photo_url'))) {
                        return '—';
                    }

                    return Thumbnails::make(
                        route('admin.biometrics.photo', $item->id)
                    );
                }),
            Image::make('e-Gov Pasport Fotosurati', 'passport_photo_url')
                ->changePreview(function ($value, $field) {
                    $item = $field->getData()?->getOriginal();
                    if (!$item || empty($item->passport_photo_url)) {
                        return '—';
                    }

                    return Thumbnails::make(
                        route('admin.biometrics.passport_photo', $item->id)
                    );
                }),
            Text::make('F.I.Sh (Lotincha)', 'full_name'),
            Text::make('JSHSHIR (PINFL)', 'pinfl'),
            Text::make('Pasport Seriya va Raqam', 'document'),
            Date::make("Tug'ilgan sana", 'birth_date')->format('d.m.Y'),
            Text::make("Tug'ilgan joyi", 'birth_place'),
            Text::make('Mamlakat / Fuqarolik', 'citizenship'),
            Text::make('Millati', 'nationality'),
            Text::make('Jinsi', 'gender_text'),
            Text::make('Hujjat berilgan joy', 'doc_give_place'),
            Date::make('Hujjat berilgan sana', 'doc_date_begin')->format('d.m.Y'),
            Date::make('Amal qilish muddati', 'doc_date_end')->format('d.m.Y'),
            Text::make('Telefon', 'phone_number'),
            Text::make('Bo\'lim / Maqsad', 'department'),
            Text::make('Biometrik Holati', 'status')
                ->badge(fn($status) => match($status) {
                    'approved' => 'green',
                    default => 'gray',
                }),
            Date::make('Tizimga kirish vaqti', 'created_at')
                ->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param  TableBuilder  $component
     *
     * @return TableBuilder
     */
    protected function modifyDetailComponent(ComponentContract $component): ComponentContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }
}
