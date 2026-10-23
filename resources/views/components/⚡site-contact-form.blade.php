<?php

use App\Actions\Site\StoreContactMessage;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    public function submit(StoreContactMessage $storeContactMessage): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $storeContactMessage->handle($validated);

        $this->reset('name', 'email', 'subject', 'message');

        Flux::toast(variant: 'success', text: __('Your message has been sent. Our team will get back to you soon.'));
    }
}; ?>

<form wire:submit="submit" class="grid gap-5" data-test="contact-form">
    <div class="grid gap-5 sm:grid-cols-2">
        <flux:field>
            <flux:input label="Full name" wire:model="name" placeholder="Jane Doe" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:input label="Email address" type="email" wire:model="email" placeholder="jane@example.com" required />
            <flux:error name="email" />
        </flux:field>
    </div>

    <flux:field>
        <flux:input label="Subject (optional)" wire:model="subject" placeholder="Admissions question" />
        <flux:error name="subject" />
    </flux:field>

    <flux:field>
        <flux:textarea label="Your message" wire:model="message" rows="5" placeholder="Tell us what you would like to know..." required />
        <flux:error name="message" />
    </flux:field>

    <flux:button variant="primary" type="submit" data-test="contact-submit">
        Send message
    </flux:button>
</form>
