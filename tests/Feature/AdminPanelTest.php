<?php

namespace Tests\Feature;

use App\Enums\LocationStatus;
use App\Enums\PhotoStatus;
use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Filament\Resources\PhotoResource\Pages\ListPhotos;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\ModerationStatsWidget;
use App\Models\Location;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Админка модерации (Filament, «/admin»): доступ только пользователям
 * с is_admin = true и рабочие действия очереди «Одобрить»/«Отклонить».
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_user_without_admin_flag_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_all_admin_sections(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            '/admin',                    // дашборд со виджетом модерации
            '/admin/photos',             // Модерация → Фотографии
            '/admin/locations',          // Модерация → Локации
            '/admin/categories',         // Каталог → Категории
            '/admin/users',              // Администрирование → Пользователи
            '/admin/manage-moderation',  // Настройки → Правила модерации
        ] as $path) {
            $this->get($path)->assertSuccessful();
        }
    }

    public function test_dashboard_shows_moderation_queue_widget(): void
    {
        $this->actingAs($this->admin());
        Location::factory()->create(['status' => LocationStatus::Pending]);
        Photo::factory()->create(['status' => PhotoStatus::Pending]);

        // Дашборд подтягивает виджеты отдельными Livewire-запросами,
        // поэтому проверяем сам виджет очереди модерации.
        Livewire::test(ModerationStatsWidget::class)
            ->assertSee('Фото на модерации')
            ->assertSee('Локации на модерации')
            ->assertSee('Требуют решения');
    }

    public function test_photo_queue_shows_pending_photo_and_approve_action_works(): void
    {
        $this->actingAs($this->admin());
        $photo = Photo::factory()->create(['status' => PhotoStatus::Pending]);

        Livewire::test(ListPhotos::class)
            ->assertCanSeeTableRecords([$photo])
            ->callTableAction('approve', $photo)
            ->assertHasNoTableActionErrors();

        $this->assertSame(PhotoStatus::Approved, $photo->fresh()->status);
    }

    public function test_photo_reject_action_stores_reason_for_author(): void
    {
        $this->actingAs($this->admin());
        $photo = Photo::factory()->create(['status' => PhotoStatus::Pending]);

        Livewire::test(ListPhotos::class)
            ->callTableAction('reject', $photo, data: ['moderation_note' => 'Слишком размыто'])
            ->assertHasNoTableActionErrors();

        $photo->refresh();
        $this->assertSame(PhotoStatus::Rejected, $photo->status);
        $this->assertSame('Слишком размыто', $photo->moderation_note);
    }

    public function test_location_queue_shows_pending_location_and_approve_action_works(): void
    {
        $this->actingAs($this->admin());
        $location = Location::factory()->create(['status' => LocationStatus::Pending]);

        Livewire::test(ListLocations::class)
            ->assertCanSeeTableRecords([$location])
            ->callTableAction('approve', $location)
            ->assertHasNoTableActionErrors();

        $this->assertSame(LocationStatus::Approved, $location->fresh()->status);
    }

    public function test_users_section_lists_users_and_grants_admin_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($this->admin());

        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$user]);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['is_admin' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->fresh()->is_admin);
    }
}
