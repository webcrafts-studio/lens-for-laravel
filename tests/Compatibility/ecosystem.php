<?php

use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;

$app = require __DIR__.'/bootstrap.php';
$tested = [];
if (InstalledVersions::isInstalled('livewire/livewire')) {
    class LensCompatibilityCounter extends Component
    {
        public int $count = 2;

        public function render(): string
        {
            return '<div><span>{{ $count }}</span><button wire:click="$increment(\'count\')">Increment</button></div>';
        }
    }
    Livewire::component('lens-compatibility-counter', LensCompatibilityCounter::class);
    $html = (string) Livewire::mount('lens-compatibility-counter');
    lensCheck(str_contains($html, 'wire:id=') && str_contains($html, '<span>2</span>'), 'Livewire component rendering failed');
    $tested[] = 'livewire/livewire';
}
if (InstalledVersions::isInstalled('inertiajs/inertia-laravel')) {
    $request = Request::create('/inertia');
    $request->headers->set('X-Inertia', 'true');
    $response = Inertia::render('LensCompatibility', ['value' => 42])->toResponse($request);
    $page = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    lensCheck($page['component'] === 'LensCompatibility' && $page['props']['value'] === 42, 'Inertia page response failed');
    $tested[] = 'inertiajs/inertia-laravel';
}
if (InstalledVersions::isInstalled('filament/filament')) {
    $panel = Panel::make()->id('lens-compatibility')->path('admin');
    app(PanelRegistry::class)->register($panel);
    lensCheck(Filament::getPanel('lens-compatibility')->getPath() === 'admin', 'Filament panel registration failed');
    lensCheck(TextInput::make('accessible_name')->getName() === 'accessible_name', 'Filament form construction failed');
    $tested[] = 'filament/filament';
}
if (InstalledVersions::isInstalled('spatie/laravel-permission')) {
    $path = dirname((new ReflectionClass(PermissionServiceProvider::class))->getFileName(), 2);
    $migration = require $path.'/database/migrations/create_permission_tables.php.stub';
    $migration->up();
    $permission = Permission::create(['name' => 'lens.scan', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'lens-auditor', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    lensCheck($role->hasPermissionTo('lens.scan'), 'Permission role assignment failed');
    $tested[] = 'spatie/laravel-permission';
}
if (InstalledVersions::isInstalled('laravel/sanctum')) {
    class LensCompatibilityUser extends User
    {
        use HasApiTokens;

        protected $table = 'users';

        protected $guarded = [];
    }
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    $path = dirname((new ReflectionClass(SanctumServiceProvider::class))->getFileName(), 2);
    foreach (glob($path.'/database/migrations/*.php') as $migration) {
        if (! Schema::hasTable('personal_access_tokens')) {
            (require $migration)->up();
        }
    }
    $user = LensCompatibilityUser::create(['name' => 'Lens auditor']);
    $token = $user->createToken('lens-fixture', ['lens:scan']);
    lensCheck(PersonalAccessToken::findToken($token->plainTextToken)?->can('lens:scan') === true, 'Sanctum token persistence failed');
    $tested[] = 'laravel/sanctum';
}
$commands = app(Kernel::class)->all();
foreach (['laravel/horizon' => 'horizon:status', 'laravel/telescope' => 'telescope:clear', 'laravel/boost' => 'boost:install'] as $package => $command) {
    if (InstalledVersions::isInstalled($package)) {
        lensCheck(isset($commands[$command], $commands['lens:audit'], $commands['lens:mcp']), $package.': commands collided');
        $tested[] = $package;
    }
}
foreach (['barryvdh/laravel-debugbar', 'fruitcake/laravel-debugbar'] as $package) {
    if (InstalledVersions::isInstalled($package)) {
        lensCheck(app()->bound('debugbar'), 'Debugbar service missing');
        $tested[] = $package;
    }
}
echo json_encode(['packages' => $tested, 'checks' => $GLOBALS['lens_checks'] ?? 0], JSON_THROW_ON_ERROR).PHP_EOL;
