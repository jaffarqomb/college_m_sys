<x-layouts::site title="About">
    {{-- Header --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-indigo-700 to-violet-700">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,.35) 1px, transparent 1px); background-size: 22px 22px;"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-16 text-center sm:px-6 sm:py-20">
            <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">About Automated College System</h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-indigo-100">
                We believe running a college should feel effortless. Our platform automates the
                administrative backbone so people can focus on learning and teaching.
            </p>
        </div>
    </section>

    {{-- Mission / Vision / Values --}}
    <section class="bg-white dark:bg-zinc-950">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-20 sm:px-6 md:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 p-8 dark:border-zinc-800">
                <span class="flex size-11 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                    <flux:icon.light-bulb class="size-6" />
                </span>
                <h2 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">Our mission</h2>
                <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    To give every college an intelligent, automated operations layer — enrollment,
                    scheduling, academics, and finance working as one system.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 p-8 dark:border-zinc-800">
                <span class="flex size-11 items-center justify-center rounded-xl bg-violet-600/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                    <flux:icon.sparkles class="size-6" />
                </span>
                <h2 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">Our vision</h2>
                <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    A world where no student ever sits in the wrong room at the wrong time, and no
                    instructor has to reconcile a spreadsheet to manage a class.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 p-8 dark:border-zinc-800">
                <span class="flex size-11 items-center justify-center rounded-xl bg-sky-600/10 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400">
                    <flux:icon.shield-check class="size-6" />
                </span>
                <h2 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">Our values</h2>
                <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    Reliability first — deterministic engines, audited money flows, and clear
                    role-based access for every actor in the college.
                </p>
            </div>
        </div>
    </section>

    {{-- Story timeline --}}
    <section class="bg-zinc-50 dark:bg-zinc-900/50">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
            <h2 class="text-center text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Our story</h2>

            <ol class="relative mt-12 space-y-10 border-l-2 border-indigo-200 pl-8 dark:border-zinc-800">
                @foreach ([
                    ['2023', 'The office with a spreadsheet problem', 'A registrar friend showed us the master timetable spreadsheet: 14,000 cells, four coordinators, one shared drive. We knew this could be solved with mathematics.'],
                    ['2024', 'First timetable engine', 'We prototyped a constraint solver that scheduled a full term in under a minute with zero conflicts. The first college ran on it that autumn.'],
                    ['2025', 'One platform for every office', 'Enrollment, academics, attendance, and finance joined the platform — plus AI dashboards for leadership.'],
                    ['2026', 'Automated College System', 'The full system is now live, with role-based portals for students, instructors, registrars, finance officers, and administrators.'],
                ] as $milestone)
                    <li class="relative">
                        <span class="absolute -left-[42px] flex size-8 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white ring-4 ring-white dark:ring-zinc-900">{{ $milestone[0] }}</span>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $milestone[1] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $milestone[2] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Departments --}}
    <section class="bg-white dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Academics across disciplines</h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">Twelve departments, forty programs, one automated backbone.</p>
            </div>

            <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ([
                    'Computer Science', 'Mathematics', 'Physics', 'Chemistry',
                    'Business Administration', 'Economics', 'English Literature',
                    'Mechanical Engineering', 'Electrical Engineering',
                    'Biological Sciences', 'Political Science', 'Psychology',
                ] as $dept)
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-700 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-500/10">
                        <flux:icon.building-library class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                        {{ $dept }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/50">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
            <h2 class="text-2xl font-bold text-zinc-900 dark:text-white">Want to see it in action?</h2>
            <div class="mt-6 flex flex-wrap justify-center gap-4">
                <flux:button href="{{ route('contact') }}" variant="primary">Contact our team</flux:button>
                <flux:button href="{{ route('home') }}" variant="ghost">Back home</flux:button>
            </div>
        </div>
    </section>
</x-layouts::site>
