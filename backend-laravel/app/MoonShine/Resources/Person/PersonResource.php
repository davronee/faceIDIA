<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Person;

use App\Models\Person;
use App\MoonShine\Resources\Person\Pages\PersonIndexPage;
use App\MoonShine\Resources\Person\Pages\PersonFormPage;
use App\MoonShine\Resources\Person\Pages\PersonDetailPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<Person, PersonIndexPage, PersonFormPage, PersonDetailPage>
 */
class PersonResource extends ModelResource
{
    protected string $model = Person::class;

    protected string $title = 'Fuqarolar (Biometrik Baza)';
    
    /**
     * @return ListOf<Action>
     */
    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [
            Action::VIEW,
            Action::DELETE,
            Action::MASS_DELETE,
        ]);
    }
    
    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            PersonIndexPage::class,
            PersonDetailPage::class,
        ];
    }
}
