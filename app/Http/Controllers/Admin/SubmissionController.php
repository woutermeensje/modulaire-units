<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');

        return view('admin.submissions.index', [
            'submissions' => Submission::query()
                ->when($type, fn ($query) => $query->where('type', $type))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'type' => $type,
        ]);
    }

    public function show(Submission $submission): View
    {
        if ($submission->read_at === null) {
            $submission->update(['read_at' => now()]);
        }

        return view('admin.submissions.show', ['submission' => $submission]);
    }

    public function download(Submission $submission): StreamedResponse
    {
        $path = $submission->data['cv_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $submission->data['cv_name'] ?? basename($path));
    }

    public function destroy(Submission $submission): RedirectResponse
    {
        if ($path = $submission->data['cv_path'] ?? null) {
            Storage::disk('local')->delete($path);
        }

        $submission->delete();

        return redirect()->route('admin.submissions.index')->with('status', 'Inzending verwijderd.');
    }
}
