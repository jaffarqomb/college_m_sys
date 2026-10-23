<x-layouts::site title="Home">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-700">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,.35) 1px, transparent 1px); background-size: 22px 22px;"></div>
        <div class="absolute -top-24 -right-24 size-96 rounded-full bg-violet-400/30 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-24 size-96 rounded-full bg-indigo-300/25 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-white backdrop-blur">
                    <flux:icon.sparkles class="size-4" />
                    AI Timetable &amp; Academics
                </span>

                <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Run your college on <span class="bg-gradient-to-r from-sky-300 to-violet-200 bg-clip-text text-transparent">autopilot</span>.
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-indigo-100">
                    The Automated College System handles enrollments, generates conflict-free AI timetables,
                    tracks attendance and grades, and manages tuition — so your staff can focus on teaching.
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <flux:button href="{{ route('register') }}" variant="primary" class="!bg-white !text-indigo-700 hover:!bg-indigo-50 shadow-lg shadow-indigo-900/30">
                        Get started free
                    </flux:button>
                    <flux:button href="{{ route('contact') }}" variant="subtle" class="!border-white/25 !bg-white/10 !text-white backdrop-blur hover:!bg-white/20">
                        Book a campus tour
                    </flux:button>
                </div>

                <p class="mt-6 flex items-center gap-2 text-sm text-indigo-200">
                    <flux:icon.check-circle class="size-5 text-emerald-300" />
                    No credit card required &bull; Sandbox demo data included
                </p>
            </div>

            <div class="pointer-events-none absolute right-8 bottom-0 hidden w-[380px] lg:block">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-xl">
                    <p class="flex items-center gap-2 text-sm font-semibold text-white">
                        <flux:icon.cpu-chip class="size-5 text-violet-200" />
                        Timetable Engine
                    </p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-xs text-white">
                            <span>CS101 · Room A-12</span><span class="text-emerald-300">✓ Scheduled</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-xs text-white">
                            <span>MA204 · Lab B-03</span><span class="text-emerald-300">✓ Scheduled</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-xs text-white">
                            <span>0 conflicts detected</span><span class="text-sky-300">constraint solver</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto grid max-w-6xl grid-cols-2 gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
            @foreach ([
                '2400+' => 'Active students',
                '40+' => 'Programs & courses',
                '12' => 'Academic departments',
                '97%' => 'Staff satisfaction',
            ] as $stat => $label)
                <div class="text-center">
                    <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 sm:text-4xl">{{ $stat }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="bg-zinc-50 dark:bg-zinc-900/50">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">Everything a modern college needs</h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">One platform connecting students, instructors, registrars, finance, and leadership.</p>
            </div>

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['icon' => 'cpu-chip', 'title' => 'AI Timetable Generation', 'text' => 'A constraint-solving engine builds conflict-free schedules for rooms, instructors, and student groups — in minutes, not weeks.'],
                    ['icon' => 'book-open', 'title' => 'Academics & Grades', 'text' => 'Assessments, weighted gradebooks, GPA, and transcripts that update the moment instructors post scores.'],
                    ['icon' => 'user-plus', 'title' => 'Smart Enrollment', 'text' => 'Students enroll with automatic checks for capacity, prerequisites, and schedule conflicts.'],
                    ['icon' => 'document-check', 'title' => 'Attendance Tracking', 'text' => 'Class sessions and attendance in two clicks, with participation insights per student and course.'],
                    ['icon' => 'banknotes', 'title' => 'Finance & Payments', 'text' => 'Term invoices, online payments, and real-time balances with an audit trail for every transaction.'],
                    ['icon' => 'chart-bar', 'title' => 'Dashboards & Reports', 'text' => 'Live KPIs for leadership — enrollment trends, revenue, attendance rates, and grade distributions.'],
                ] as $feature)
                    <div class="group rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-500/15 dark:text-indigo-400">
                            <flux:icon :name="$feature['icon']" class="size-6" />
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-zinc-900 dark:text-white">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $feature['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">Up and running in three steps</h2>
            </div>

            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['number' => '1', 'title' => 'Create accounts', 'text' => 'Staff set up departments, programs, and student accounts in bulk. Students receive sign-in credentials instantly.'],
                    ['number' => '2', 'title' => 'Run the AI timetable', 'text' => 'One click generates the term schedule. Review conflicts, drag fixes, and publish to every portal.'],
                    ['number' => '3', 'title' => 'Watch it hum', 'text' => 'Enrollments, grades, attendance, and invoices flow automatically with notifications for every event.'],
                ] as $step)
                    <div class="rounded-2xl border border-zinc-200 p-8 dark:border-zinc-800">
                        <span class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-bold text-white">{{ $step['number'] }}</span>
                        <h3 class="mt-5 text-lg font-semibold text-zinc-900 dark:text-white">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $step['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-indigo-700 to-violet-700">
        <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(rgba(255,255,255,.4) 1px, transparent 1px); background-size: 26px 26px;"></div>
        <div class="relative mx-auto max-w-4xl px-4 py-20 text-center sm:px-6">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Ready to automate your college?</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-indigo-100">Join the colleges saving hundreds of staff hours every term.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <flux:button href="{{ route('register') }}" variant="primary" class="!bg-white !text-indigo-700 hover:!bg-indigo-50">Create your account</flux:button>
                <flux:button href="{{ route('contact') }}" variant="subtle" class="!border-white/25 !bg-white/10 !text-white hover:!bg-white/20">Talk to us</flux:button>
            </div>
        </div>
    </section>
</x-layouts::site>
