<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();
    @endphp

    @if ($user->role->isStudent() && $user->studentProfile !== null)
        <div class="space-y-6">
            @if (session('toast'))
                <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-6 py-4 text-sm font-medium text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200" data-test="flash-toast">
                    {{ session('toast') }}
                </div>
            @endif

            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Welcome, {{ $user->studentProfile->displayName() }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $user->studentProfile->student_number }}
                    @if ($user->studentProfile->program !== null)
                        · {{ $user->studentProfile->program->name }}
                    @endif
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <livewire:portal-my-enrollments />
                <livewire:portal-open-sections />
                <livewire:portal-gradebook />
                <livewire:portal-invoices />
            </div>
        </div>
    @elseif ($user->role->isStudent())
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <flux:icon.academic-cap class="mx-auto size-10 text-zinc-400" />
            <h1 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-100">No student profile yet</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Your account does not have a student record yet. Please contact the registrar's office.
            </p>
        </div>
    @elseif ($user->role->isInstructor() && $user->instructorProfile !== null)
        <div class="space-y-6">
            <livewire:instructor-sections />
            <livewire:attendance-marking />
            <livewire:instructor-gradebook />
        </div>
    @elseif ($user->role->isInstructor())
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <flux:icon.academic-cap class="mx-auto size-10 text-zinc-400" />
            <h1 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-100">No instructor profile yet</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Your account does not have an instructor record yet. Please contact the registrar's office.
            </p>
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <flux:icon.shield-check class="mx-auto size-10 text-indigo-500" />
            <h1 class="mt-3 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Staff access</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                You are signed in as a {{ $user->role->label() }}. Manage academic records in the admin panel.
            </p>
            <flux:button href="{{ url('/admin') }}" variant="primary" class="mt-4">
                Open admin panel
            </flux:button>
        </div>
    @endif
</x-layouts::app>
