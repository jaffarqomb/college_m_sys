<x-layouts::site title="Contact">
    {{-- Header --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-indigo-700 to-violet-700">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,.35) 1px, transparent 1px); background-size: 22px 22px;"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-16 text-center sm:px-6 sm:py-20">
            <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">Get in touch</h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-indigo-100">
                Questions about admissions, programs, or a live demo? Our team replies within one business day.
            </p>
        </div>
    </section>

    <section class="bg-white dark:bg-zinc-950">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-5">
            {{-- Contact info --}}
            <div class="space-y-4 lg:col-span-2">
                <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                        <flux:icon.envelope class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Email us</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ config('site.contact.email') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                        <flux:icon.phone class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Call us</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ config('site.contact.phone') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                        <flux:icon.map-pin class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Visit us</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ config('site.contact.address') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400">
                        <flux:icon.clock class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Office hours</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ config('site.contact.hours') }}</p>
                    </div>
                </div>
            </div>

            {{-- Form --}}
            <div class="rounded-2xl border border-zinc-200 p-6 sm:p-8 lg:col-span-3 dark:border-zinc-800">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Send us a message</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Fields marked required cannot left empty.</p>
                <div class="mt-6">
                    <livewire:site-contact-form />
                </div>
            </div>
        </div>
    </section>
</x-layouts::site>
