<?php

/** @noinspection PhpUndefinedMethodInspection */

/** @noinspection LaravelFunctionsInspection */

use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use DefStudio\Telegraph\Telegraph;
use DefStudio\Telegraph\Tests\Support\TestWebhookHandler;
use DefStudio\Telegraph\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

uses(TestCase::class)->in("Feature");
uses(TestCase::class)->in("Unit");
uses(TestCase::class)->in("Regression");
uses(TestCase::class)->group('sandbox')->in("Sandbox");

function withfakeUrl(): string
{
    $url = 'https://testbot.defstudio.dev';
    URL::forceScheme('https');
    URL::forceRootUrl($url);

    return $url;
}

/**
 * @return Collection<TelegraphBot>
 */
function bots(int $count): Collection
{
    return TelegraphBot::factory()->count($count)->create();
}

function sandbox_bot(): TelegraphBot
{
    return bot(env('SANDOBOX_TELEGRAM_BOT_TOKEN'), env('SANDBOX_TELEGRAM_CHAT_ID'));
}

function bot(string $token = '3f3814e1-5836-3d77-904e-60f64b15df36', string $chatId = '-123456789'): TelegraphBot
{
    /** @var TelegraphBot $bot */
    $bot = TelegraphBot::factory(['token' => $token])
        ->create();

    $bot->chats()->save(TelegraphChat::factory(['chat_id' => $chatId, 'telegraph_bot_id' => null])->make());

    return $bot->refresh();
}

function make_bot(): TelegraphBot
{
    $bot = TelegraphBot::factory(['token' => '3f3814e1-5836-3d77-904e-60f64b15df36'])->make();
    $bot->setRelation('chats', Collection::make([TelegraphChat::factory(['chat_id' => '-123456789'])->make()]));

    return $bot;
}

function chat(): TelegraphChat
{
    return TelegraphChat::factory(['chat_id' => '-123456789'])->create();
}

function make_chat(): TelegraphChat
{
    $bot = make_bot();
    $chat = TelegraphChat::factory([
        'chat_id' => '-123456789',
        'telegraph_bot_id' => null,
    ])->make();

    $chat->setRelation('bot', $bot);
    $bot->setRelation('chats', Collection::make([$chat]));

    return $chat;
}

function register_webhook_handler(string $handler = TestWebhookHandler::class): void
{
    if ($handler == TestWebhookHandler::class) {
        TestWebhookHandler::reset();
    }
    config()->set('telegraph.webhook.handler', $handler);
}

function webhook_message($handler = TestWebhookHandler::class, array $message = null): Request
{
    register_webhook_handler($handler);

    return Request::create('', 'POST', [
        'message' => $message ?? [
            'message_id' => 123456,
            'chat' => [
                'id' => 123456,
                'type' => 'group',
                'title' => 'Test chat',
            ],
            "text" => 'foo',
        ],
    ]);
}

function webhook_request($action = 'invalid', $handler = TestWebhookHandler::class, int $chat_id = -123456789): Request
{
    register_webhook_handler($handler);

    return Request::create('', 'POST', [
        'callback_query' => [
            'id' => 159753,
            'from' => [
                'id' => 8974,
                'is_bot' => true,
                'first_name' => 'Test Bot',
                'username' => 'test_bot',
                'can_join_groups' => true,
                'can_read_all_group_messages' => false,
                'supports_inline_queries' => false,
            ],
            'message' => [
                'message_id' => 123456,
                'chat' => [
                    'id' => $chat_id,
                    'type' => 'group',
                    'title' => 'Test chat',
                ],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ["text" => "test", "callback_data" => "action:test;id:1"],
                            ["text" => "delete", "callback_data" => "action:delete;id:2"],
                        ],
                        [
                            ["text" => "👀 Apri", "url" => 'https://test.it'],
                        ],
                    ],
                ],
                'date' => 1646516736,
            ],
            'data' => "action:$action",
        ],
    ]);
}

function webhook_command($command, $handler = TestWebhookHandler::class, int $chat_id = -123456789): Request
{
    register_webhook_handler($handler);

    return Request::create('', 'POST', [
        'message' => [
            'message_id' => 123456,
            'chat' => [
                'id' => $chat_id,
                'type' => 'private',
                'username' => 'john-smith',
            ],
            'text' => $command,
            'date' => 1646516736,
        ],
    ]);
}

function webhook_inline_query($handler = TestWebhookHandler::class): Request
{
    register_webhook_handler($handler);

    return Request::create('', 'POST', [
        'inline_query' => [
            'id' => "a99",
            'query' => 'foo',
            'from' => [
                'id' => 1,
                'is_bot' => false,
                'first_name' => 'e',
                'last_name' => 'f',
                'username' => 'g',
            ],
            'offset' => '+4',
            'chat_type' => 'private',
        ],
    ]);
}


expect()->extend('toMatchTelegramSnapshot', function () {
    /** @var Closure(Telegraph): Telegraph $configurationClosure */
    $configurationClosure = $this->value;

    /** @var Telegraph $telegraph */
    $telegraph = app(Telegraph::class)->chat(make_chat());

    $telegraph = $configurationClosure($telegraph);

    expect($telegraph->toArray())->toMatchSnapshot();
});
