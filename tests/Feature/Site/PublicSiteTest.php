<?php

use App\Actions\Site\ResolveVisitorChat;
use App\Actions\Site\StoreContactMessage;
use App\Models\ChatMessage;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('public site pages render for guests', function () {
    $this->get(route('home'))->assertOk()->assertSee('Run your college on');
    $this->get(route('about'))->assertOk();
    $this->get(route('contact'))->assertOk()->assertSee('Send us a message');
});

test('contact form validates input', function () {
    Livewire::test('site-contact-form')
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('message', 'short')
        ->call('submit')
        ->assertHasErrors([
            'name' => 'required',
            'email' => 'email',
            'message' => 'min',
        ]);
});

test('contact form persists the message and notifies the admissions team', function () {
    Notification::fake();

    Livewire::test('site-contact-form')
        ->set('name', 'Jane Visitor')
        ->set('email', 'jane@example.com')
        ->set('subject', 'Programs')
        ->set('message', 'I would like to know more about the computer science program.')
        ->call('submit')
        ->assertOk();

    assertDatabaseHas(ContactMessage::class, [
        'name' => 'Jane Visitor',
        'email' => 'jane@example.com',
        'subject' => 'Programs',
    ]);

    Notification::assertSentOnDemand(ContactMessageReceived::class);
});

test('contact message action returns the persisted message', function () {
    Notification::fake();

    $action = app(StoreContactMessage::class);

    $contactMessage = $action->handle([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => null,
        'message' => 'Hello, this is a test of the store action.',
    ]);

    expect($contactMessage->exists)->toBeTrue()
        ->and($contactMessage->is_read)->toBeFalse();
});

test('faq resolver matches configured intents', function () {
    $resolver = app(ResolveVisitorChat::class);

    expect($resolver->handle('What programs do you offer?')['intent'])->toBe('programs')
        ->and($resolver->handle('How much are the tuition fees?')['intent'])->toBe('fees')
        ->and($resolver->handle('Tell me about the AI timetable')['intent'])->toBe('timetable')
        ->and($resolver->handle('Hello there')['intent'])->toBe('greeting')
        ->and($resolver->handle('banana pikachu')['intent'])->toBeNull()
        ->and($resolver->handle('banana pikachu')['content'])->toBe(config('chatbot.messages.fallback'));
});

test('chatbot greets visitors when mounted and opened', function () {
    Livewire::test('site-chatbot')
        ->assertDontSee('Campus Assistant')
        ->set('open', true)
        ->assertSee('Campus Assistant')
        ->assertSee(config('chatbot.messages.greeting'));
});

test('chatbot answers questions and persists the conversation', function () {
    Livewire::test('site-chatbot')
        ->set('question', 'What programs do you offer?')
        ->call('send')
        ->set('question', 'random gibberish question xyz')
        ->call('send')
        ->assertSet('question', '');

    assertDatabaseCount('chat_messages', 4); // 2 sends, each persisting a user + bot message

    assertDatabaseHas(ChatMessage::class, [
        'role' => 'user',
        'content' => 'What programs do you offer?',
    ]);

    assertDatabaseHas(ChatMessage::class, [
        'role' => 'bot',
        'intent' => 'programs',
    ]);

    assertDatabaseCount('chat_sessions', 1);
});

test('chatbot rate limits rapid questions', function () {
    config(['chatbot.cooldown' => 60]);

    Livewire::test('site-chatbot')
        ->set('question', 'what courses exist?')
        ->call('send')
        ->set('question', 'what about fees?')
        ->call('send');

    assertDatabaseHas(ChatMessage::class, [
        'role' => 'bot',
        'content' => config('chatbot.messages.rate_limited'),
    ]);
});

test('chatbot ignores empty questions', function () {
    Livewire::test('site-chatbot')
        ->set('question', '   ')
        ->call('send')
        ->assertSet('question', '   ');

    assertDatabaseCount('chat_messages', 0);
});
