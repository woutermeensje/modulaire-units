<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Submission;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Nieuwe inzendingen', 'value' => Submission::query()->whereNull('read_at')->count(), 'icon' => 'ph-tray', 'route' => 'admin.submissions.index'],
                ['label' => 'Units', 'value' => Unit::query()->count(), 'icon' => 'ph-cube', 'route' => 'admin.units.index'],
                ['label' => "Pagina's", 'value' => Page::query()->count(), 'icon' => 'ph-files', 'route' => 'admin.pages.index'],
                ['label' => 'Blogs', 'value' => Post::query()->count(), 'icon' => 'ph-newspaper-clipping', 'route' => 'admin.posts.index'],
                ['label' => 'Vacatures', 'value' => Vacancy::query()->count(), 'icon' => 'ph-briefcase', 'route' => 'admin.vacancies.index'],
                ['label' => 'Klantaccounts', 'value' => User::query()->where('is_admin', false)->count(), 'icon' => 'ph-users', 'route' => 'admin.users.index'],
            ],
            'latestSubmissions' => Submission::query()->latest()->limit(8)->get(),
        ]);
    }
}
