<?php

namespace App\Tests\Unit\Import\Telegram;

use App\Import\Telegram\Channel;
use App\Import\Telegram\Message;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class MessageTest extends TestCase
{
    public static function markupDataProvider(): iterable
    {
        yield 'Plaintext' => [
            'input' => 'plaintext',
            'expected' => 'plaintext',
        ];

        yield 'Bold' => [
            'input' => ['type' => 'bold', 'text' => 'bold'],
            'expected' => '**bold**',
        ];

        yield 'Italic' => [
            'input' => ['type' => 'italic', 'text' => 'italic'],
            'expected' => '*italic*',
        ];

        yield 'Strikethrough' => [
            'input' => ['type' => 'strikethrough', 'text' => 'strikethrough'],
            'expected' => '~~strikethrough~~',
        ];

        yield 'Code' => [
            'input' => ['type' => 'code', 'text' => 'code'],
            'expected' => '`code`',
        ];

        yield 'Code block with no language' => [
            'input' => ['type' => 'pre', 'text' => 'pre_generic', 'language' => ''],
            'expected' => "```\npre_generic\n```",
        ];

        yield 'Code block with language' => [
            'input' => ['type' => 'pre', 'text' => 'pre_php', 'language' => 'php'],
            'expected' => "```php\npre_php\n```",
        ];

        yield 'Text link' => [
            'input' => ['type' => 'text_link', 'text' => 'text_link', 'href' => 'https://example.com'],
            'expected' => '[text_link](https://example.com)',
        ];

        yield 'Link with URL' => [
            'input' => ['type' => 'link', 'text' => 'https://example.com'],
            'expected' => '[https://example.com](https://example.com)',
        ];

        yield 'Link with domain' => [
            'input' => ['type' => 'link', 'text' => 'example.com'],
            'expected' => '[example.com](https://example.com)',
        ];

        yield 'Email' => [
            ['type' => 'email', 'text' => 'test@example.com'],
            'expected' => '[test@example.com](mailto:test@example.com)',
        ];

        yield 'Blockquote' => [
            'input' => ['type' => 'blockquote', 'text' => "blockquote1\nblockquote2"],
            'expected' => "> blockquote1\n> blockquote2",
        ];

        yield 'Mention' => [
            'input' => ['type' => 'mention', 'text' => '@mention'],
            'expected' => "[@mention](https://t.me/mention)",
        ];

        yield 'Hashtags' => [
            'input' => ['type' => 'hashtag', 'text' => '#php'],
            'expected' => "#php",
        ];
    }

    #[DataProvider('markupDataProvider')]
    public function testConvertsMarkup(string|array $input, string $expected): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date_unixtime' => '1667380570',
            'edited_unixtime' => '1671535849',
            'text' => [
                "Markup test\n", // <-- becomes title and is removed from content
                $input
            ],
            'text_entities' => [],
        ], null);

        assertStringContainsString($expected, $message->markdown);
    }

    public function testDeduplicatesHashtags(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => [
                'Post ',
                ['type' => 'hashtag', 'text' => '#php'],
                ' ',
                ['type' => 'hashtag', 'text' => '#PHP'],
            ],
            'text_entities' => [],
        ], null);

        assertSame(['php'], $message->tags);
    }

    public function testExtractsTitleFromMarkdown(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => [
                ['type' => 'bold', 'text' => '🎁 YiiPress'],
                "\n\nI always wanted to build a really fast static website generator engine with PHP. There were multiple reasons to do it:\n\n1. To exercise.\n2. For my own needs. I want to combine all posts I've ever made in a single indexable place.\n3. To battle-test Yii3 one more time with a non-standard case.\n4. To try LLM assisted coding in action.\n\nSo here we go, YiiPress was born. It's in alpha stage but works quite well and ",
                ['type' => 'text_link', 'text' => 'can build its own docs', 'href' => 'https://yiipress.github.io/engine/'],
                ".\n\nGo try and explore it: ",
                ['type' => 'link', 'text' => 'https://github.com/yiipress/engine'],
                "\n\n",
                ['type' => 'hashtag', 'text' => '#yii'],
                ' ',
                ['type' => 'hashtag', 'text' => '#yiipress'],
                ''
            ],
            'text_entities' => [
                ['type' => 'bold', 'text' => '🎁 YiiPress'],
                ['type' => 'plain', 'text' => "\n\nI always wanted to build a really fast static website generator engine with PHP. There were multiple reasons to do it:\n\n1. To exercise.\n2. For my own needs. I want to combine all posts I've ever made in a single indexable place.\n3. To battle-test Yii3 one more time with a non-standard case.\n4. To try LLM assisted coding in action.\n\nSo here we go, YiiPress was born. It's in alpha stage but works quite well and "],
                ['type' => 'text_link', 'text' => 'can build its own docs', 'href' => 'https://yiipress.github.io/engine/'],
                ['type' => 'plain', 'text' => ".\n\nGo try and explore it: "],
                ['type' => 'link', 'text' => 'https://github.com/yiipress/engine'],
                ['type' => 'plain', 'text' => "\n\n"],
                ['type' => 'hashtag', 'text' => '#yii'],
                ['type' => 'plain', 'text' => ' '],
                ['type' => 'hashtag', 'text' => '#yiipress'],
                ['type' => 'plain', 'text' => '']
            ],
        ], null);

        assertSame('🎁 YiiPress', $message->title);
    }

    /**+
     * In Telegram markup \n between \n\n and \n\n are converted to \n\n as well.
     */
    public function testImplicitLists(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => [
                ['type' => 'bold', 'text' => "Присоединяйтесь к исследованию отрасли!\n"],
                ['type' => 'bold', 'text' => "Для этого нам важно услышать именно ваш опыт\n\n"],
                ":\n✔️какие управленческие задачи сейчас в фокусе вашего внимания;\n✔️с какими профессиональными вызовами вы сталкиваетесь при выстраивании процессов и работе с командой;\n✔️каких тематических блоков или форматов вам недостает на профильных мероприятиях.\n\nЧтобы собрать репрезентативные данные, мы разработали краткий опрос. Его заполнение займет не более 3 минут.\n\n",
            ],
            'text_entities' => [
            ],
        ], null);

        assertStringContainsString(
            <<<EXPECTED
**Для этого нам важно услышать именно ваш опыт**:

✔️какие управленческие задачи сейчас в фокусе вашего внимания;

✔️с какими профессиональными вызовами вы сталкиваетесь при выстраивании процессов и работе с командой;

✔️каких тематических блоков или форматов вам недостает на профильных мероприятиях.
EXPECTED
            , $message->markdown);
    }

    public function testGeneratesSlugFromTitle(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Hello from Telegram channel',
            'text_entities' => [
                ['type' => 'plain', 'text' => 'Hello from Telegram channel'],
            ],
        ], null);

        assertSame('hello-from-telegram-channel', $message->slug);
    }

    public function testHandlesDateConversion(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Test',
            'text_entities' => [['type' => 'plain', 'text' => 'Test']],
        ], null);

        $expected = DateTimeImmutable::createFromTimestamp(1710516600);
        assertSame($expected->format('Y-m-d H:i:s'), $message->date->format('Y-m-d H:i:s'));
    }

    public function testHandlesEditedDate(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516900',
            'text' => 'Test',
            'text_entities' => [['type' => 'plain', 'text' => 'Test']],
        ], null);

        $expected = DateTimeImmutable::createFromTimestamp(1710516900);
        assertSame($expected->format('Y-m-d H:i:s'), $message->edited->format('Y-m-d H:i:s'));
    }

    public function testGeneratesTelegramLinkWithChannel(): void
    {
        $channel = new Channel([
            'id' => 1,
            'type' => 'service',
            'date' => '2022-11-02T12:00:29',
            'date_unixtime' => '1667379629',
            'actor' => 'samdark blog ☕️ (Alexander Makarov)',
            'actor_id' => 'channel1698448697',
            'action' => 'create_channel',
            'title' => 'samdark_blog',
            'text' => '',
            'text_entities' => [],
        ]);

        $message = new Message([
            'id' => 123,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Test',
            'text_entities' => [['type' => 'plain', 'text' => 'Test']],
        ], $channel);

        assertSame('https://t.me/samdark_blog/123', $message->telegramLink);
    }

    public function testHandlesPhotoAttachment(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Check this photo',
            'text_entities' => [['type' => 'plain', 'text' => 'Check this photo']],
            'photo' => 'photos/photo_1.jpg',
        ], null);

        assertSame('photos/photo_1.jpg', $message->photo);
    }

    public function testHandlesFileAttachment(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Download file',
            'text_entities' => [['type' => 'plain', 'text' => 'Download file']],
            'file' => 'files/document.pdf',
        ], null);

        assertSame('files/document.pdf', $message->file);
    }

    public function testHandlesForwardedMessage(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Forwarded content',
            'text_entities' => [['type' => 'plain', 'text' => 'Forwarded content']],
            'forwarded_from' => 'Original Channel',
        ], null);

        assertSame('Original Channel', $message->forwardedFrom);
    }

    public function testExtractsHashtagsAsTags(): void
    {
        $message = new Message([
            'id' => 1,
            'type' => 'message',
            'date' => '2024-03-15T10:30:00',
            'date_unixtime' => '1710516600',
            'edited_unixtime' => '1710516600',
            'text' => 'Great post about PHP #php #webdev',
            'text_entities' => [
                ['type' => 'plain', 'text' => 'Great post about PHP '],
                ['type' => 'hashtag', 'text' => '#php'],
                ['type' => 'plain', 'text' => ' '],
                ['type' => 'hashtag', 'text' => '#webdev'],
            ],
        ], null);

        assertSame(['php', 'webdev'], $message->tags);
    }
}
